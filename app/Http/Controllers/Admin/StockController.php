<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StockController extends Controller
{
    public function index(Request $request)
    {
        $distributor = $request->query('distributor');
        $school      = $request->query('school');
        $subject     = $request->query('subject');
        $low         = $request->boolean('low');
        $q           = trim((string) $request->query('q'));

        // 정렬
        $allowedSorts = [
            'title'              => 'b.title',
            'isbn'               => 'b.isbn',
            'price'              => 'b.price',
            'publisher_name'     => 'p.name',
            'distributor_name'   => 'u.name',
            'qty'                => 's.qty',
            'low_stock_threshold'=> 's.low_stock_threshold',
            'reserved_qty'       => 's.reserved_qty',
        ];
        $sort = $request->query('sort', 'distributor_name');
        $dir  = $request->query('dir', 'asc');
        if (! array_key_exists($sort, $allowedSorts)) $sort = 'distributor_name';
        if (! in_array($dir, ['asc', 'desc'], true)) $dir = 'asc';

        $query = DB::table('book_stocks as s')
            ->join('books as b', 'b.id', '=', 's.book_id')
            ->leftJoin('publishers as p', 'p.id', '=', 'b.publisher_id')
            ->join('users as u', 'u.id', '=', 's.distributor_user_id')
            ->select(
                's.id', 's.qty', 's.reserved_qty', 's.low_stock_threshold',
                'b.id as book_id', 'b.isbn', 'b.title', 'b.subtitle',
                'b.price', 'b.school_code', 'b.subject_code', 'b.status_code as book_status',
                'b.cover_path',
                'p.name as publisher_name',
                'u.id as distributor_id', 'u.name as distributor_name'
            )
            ->whereNull('b.deleted_at')
            ->orderBy($allowedSorts[$sort], $dir)
            ->orderBy('b.title');

        if ($distributor) $query->where('s.distributor_user_id', $distributor);
        if ($school)      $query->where('b.school_code', $school);
        if ($subject)     $query->where('b.subject_code', $subject);
        if ($low)         $query->whereColumn('s.qty', '<=', 's.low_stock_threshold');
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('b.title', 'like', "%{$q}%")
                  ->orWhere('b.isbn', 'like', "%{$q}%");
            });
        }

        $stocks = $query->paginate(50)->withQueryString();

        $distributors = DB::table('users')
            ->where('role_code', 'distributor')
            ->where('status_code', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);
        $schoolOptions  = DB::table('codes')->where('group_code', 'school')->orderBy('sort_order')->get();
        $subjectOptions = DB::table('codes')->where('group_code', 'subject')->orderBy('sort_order')->get();

        $summary = [
            'total_lines'  => DB::table('book_stocks')->count(),
            'total_qty'    => (int) DB::table('book_stocks')->sum('qty'),
            'low_stock'    => DB::table('book_stocks')->whereColumn('qty', '<=', 'low_stock_threshold')->count(),
            'zero_stock'   => DB::table('book_stocks')->where('qty', 0)->count(),
        ];

        return view('admin.stocks.index', compact(
            'stocks', 'distributors', 'schoolOptions', 'subjectOptions',
            'distributor', 'school', 'subject', 'low', 'q', 'summary', 'sort', 'dir'
        ));
    }

    /** 인라인 단건 수정 */
    public function update(Request $request, int $stockId)
    {
        $data = $request->validate([
            'qty' => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
        ]);

        $row = DB::table('book_stocks')->where('id', $stockId)->first();
        abort_if(! $row, 404);

        $update = ['qty' => $data['qty'], 'updated_at' => now()];
        if (array_key_exists('low_stock_threshold', $data)) {
            $update['low_stock_threshold'] = $data['low_stock_threshold'] ?? 0;
        }
        DB::table('book_stocks')->where('id', $stockId)->update($update);

        return back()->with('success', '재고가 저장되었습니다.');
    }

    /** 폼에서 여러 행 한 번에 수정 */
    public function bulkUpdate(Request $request)
    {
        $rows = (array) $request->input('stocks', []);
        $count = 0;
        foreach ($rows as $stockId => $r) {
            if (! is_numeric($stockId)) continue;
            $qty = isset($r['qty']) ? (int) $r['qty'] : null;
            $low = isset($r['low_stock_threshold']) ? (int) $r['low_stock_threshold'] : null;
            if ($qty === null || $qty < 0) continue;

            DB::table('book_stocks')->where('id', (int) $stockId)->update([
                'qty' => $qty,
                'low_stock_threshold' => $low ?? 0,
                'updated_at' => now(),
            ]);
            $count++;
        }
        return back()->with('success', "{$count}건의 재고가 저장되었습니다.");
    }

    /** 새 매핑 추가 (도서 × 총판 + 초기 수량) */
    public function store(Request $request)
    {
        $data = $request->validate([
            'book_id'             => ['required', 'integer', 'exists:books,id'],
            'distributor_user_id' => ['required', 'integer', 'exists:users,id'],
            'qty'                 => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
        ]);

        if (DB::table('book_stocks')
            ->where('book_id', $data['book_id'])
            ->where('distributor_user_id', $data['distributor_user_id'])
            ->exists()) {
            return back()->with('error', '이미 등록된 도서×총판 조합입니다.');
        }
        $u = DB::table('users')->where('id', $data['distributor_user_id'])->first();
        if (! $u || $u->role_code !== 'distributor') {
            return back()->with('error', 'distributor 역할의 사용자만 선택할 수 있습니다.');
        }

        DB::table('book_stocks')->insert([
            'book_id'             => $data['book_id'],
            'distributor_user_id' => $data['distributor_user_id'],
            'qty'                 => $data['qty'],
            'low_stock_threshold' => $data['low_stock_threshold'] ?? 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return back()->with('success', '재고가 추가되었습니다.');
    }

    public function destroy(int $stockId)
    {
        DB::table('book_stocks')->where('id', $stockId)->delete();
        return back()->with('success', '재고가 삭제되었습니다.');
    }
}
