<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Publisher;
use App\Services\AladinService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BookController extends Controller
{
    public function __construct(private AladinService $aladin) {}

    // -------------------- LIST --------------------
    public function index(Request $request)
    {
        $status   = $request->query('status');
        $subject  = $request->query('subject');
        $school   = $request->query('school');
        $publisher= $request->query('publisher');
        $q = trim((string) $request->query('q'));

        // 정렬 — 허용 컬럼만 (SQL Injection 방어)
        $allowedSorts = ['id', 'title', 'isbn', 'publisher_code', 'publisher_id', 'price', 'status_code', 'school_code', 'subject_code', 'created_at'];
        $sort = $request->query('sort', 'id');
        $dir  = $request->query('dir', 'desc');
        if (! in_array($sort, $allowedSorts, true)) $sort = 'id';
        if (! in_array($dir, ['asc', 'desc'], true)) $dir = 'desc';

        $query = Book::query()->with('publisher')->orderBy($sort, $dir);
        // 보조 정렬 (동일 값일 때 안정적인 순서 유지)
        if ($sort !== 'id') $query->orderByDesc('id');
        if ($status)   $query->where('status_code', $status);
        if ($subject)  $query->where('subject_code', $subject);
        if ($school)   $query->where('school_code', $school);
        if ($publisher)$query->where('publisher_id', $publisher);
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('title', 'like', "%{$q}%")
                  ->orWhere('isbn', 'like', "%{$q}%")
                  ->orWhere('author', 'like', "%{$q}%")
                  ->orWhere('series_name', 'like', "%{$q}%")
                  ->orWhere('publisher_code', 'like', "%{$q}%");
            });
        }
        $books = $query->paginate(50)->withQueryString();

        $statusOptions    = DB::table('codes')->where('group_code', 'book_status')->orderBy('sort_order')->get();
        $subjectOptions   = DB::table('codes')->where('group_code', 'subject')->orderBy('sort_order')->get();
        $schoolOptions    = DB::table('codes')->where('group_code', 'school')->orderBy('sort_order')->get();
        // 출판사 드롭다운 — 실제 books 테이블에 도서가 등록된 출판사만 표시
        $publisherOptions = Publisher::whereIn('id', function ($sq) {
            $sq->select('publisher_id')->from('books')
               ->whereNotNull('publisher_id')->whereNull('deleted_at');
        })->orderBy('sort_order')->orderBy('name')->get(['id','name']);

        return view('admin.books.index', compact(
            'books','statusOptions','subjectOptions','schoolOptions','publisherOptions',
            'status','subject','school','publisher','q','sort','dir'
        ));
    }

    // -------------------- CREATE --------------------
    public function create()
    {
        return view('admin.books.create', $this->formData(null));
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request);
        $book = Book::create($data + ['source' => $data['source'] ?? 'manual']);
        return redirect()->route('admin.books.show', $book)->with('success', '도서가 등록되었습니다.');
    }

    // -------------------- SHOW + EDIT --------------------
    public function show(Book $book)
    {
        $extra = $this->formData($book);

        // 학년 등 N:N
        $targets = DB::table('book_targets')->where('book_id', $book->id)->get();
        $extra['gradeCodes']     = $targets->where('target_type', 'grade')->pluck('code')->toArray();
        $extra['levelCodes']     = $targets->where('target_type', 'level')->pluck('code')->toArray();
        $extra['schoolTargets']  = $targets->where('target_type', 'school')->pluck('code')->toArray();
        $extra['semesterCodes']  = $targets->where('target_type', 'semester')->pluck('code')->toArray();

        $extra['stocks'] = DB::table('book_stocks as s')
            ->join('users as u', 'u.id', '=', 's.distributor_user_id')
            ->where('s.book_id', $book->id)
            ->select('s.id','s.qty','s.low_stock_threshold','s.reserved_qty','u.id as dist_id','u.name as dist_name')
            ->get();

        return view('admin.books.show', array_merge(['book' => $book], $extra));
    }

    public function update(Request $request, Book $book)
    {
        $data = $this->validatePayload($request, $book->id);
        $book->update($data);
        $this->syncTargets($book, $request);
        return redirect()->route('admin.books.show', $book)->with('success', '저장되었습니다.');
    }

    public function destroy(Book $book)
    {
        if (DB::table('order_items')->where('book_id', $book->id)->exists()) {
            return back()->with('error', '주문 이력이 있는 도서는 삭제할 수 없습니다. 상태를 "절판"으로 변경하세요.');
        }
        $book->delete();
        return redirect()->route('admin.books.index')->with('success', '도서가 삭제되었습니다.');
    }

    // -------------------- ALADIN AJAX --------------------
    public function aladinLookup(Request $request)
    {
        $isbn = (string) $request->query('isbn', '');
        if (! $this->aladin->configured()) {
            return response()->json(['ok' => false, 'error' => '알라딘 TTB Key가 설정되지 않았습니다. 사이트 설정 > 외부 연동에서 입력하세요.'], 400);
        }
        $item = $this->aladin->lookupByIsbn($isbn);
        if (! $item) {
            return response()->json(['ok' => false, 'error' => '도서를 찾지 못했습니다.'], 404);
        }

        // 출판사 자동 매칭/생성
        $publisherId = null;
        if ($item['publisher'] !== '') {
            $pub = Publisher::firstOrCreate(
                ['name' => $item['publisher']],
                ['sort_order' => 999, 'is_active' => true]
            );
            $publisherId = $pub->id;
        }

        return response()->json([
            'ok'   => true,
            'data' => [
                'isbn'        => $item['isbn'],
                'title'       => $item['title'],
                'subtitle'    => $item['subtitle'],
                'author'      => $item['author'],
                'publisher_id'=> $publisherId,
                'publisher'   => $item['publisher'],
                'price'       => $item['price'],
                'pub_date'    => $item['pub_date'],
                'cover'       => $item['cover'],
                'description' => $item['description'],
                'category'    => $item['category'],
            ],
        ]);
    }

    public function aladinSearch(Request $request)
    {
        $q = (string) $request->query('q', '');
        if (! $this->aladin->configured()) {
            return response()->json(['ok' => false, 'error' => '알라딘 TTB Key가 설정되지 않았습니다.'], 400);
        }
        return response()->json([
            'ok'   => true,
            'data' => $this->aladin->search($q, 10),
        ]);
    }

    // -------------------- helpers --------------------
    private function formData(?Book $book = null): array
    {
        return [
            'statusOptions'    => DB::table('codes')->where('group_code', 'book_status')->orderBy('sort_order')->get(),
            'subjectOptions'   => DB::table('codes')->where('group_code', 'subject')->orderBy('sort_order')->get(),
            'schoolOptions'    => DB::table('codes')->where('group_code', 'school')->orderBy('sort_order')->get(),
            'gradeOptions'     => DB::table('codes')->where('group_code', 'grade')->orderBy('sort_order')->get(),
            'levelOptions'     => DB::table('codes')->where('group_code', 'level')->orderBy('sort_order')->get(),
            'semesterOptions'  => DB::table('codes')->where('group_code', 'semester')->orderBy('sort_order')->get(),
            'publisherOptions' => Publisher::orderBy('sort_order')->get(['id','name']),
        ];
    }

    private function validatePayload(Request $request, ?int $bookId = null): array
    {
        return $request->validate([
            'isbn'         => ['required', 'string', 'max:20',
                Rule::unique('books', 'isbn')->ignore($bookId)],
            'publisher_code' => ['nullable', 'string', 'max:50'],
            'title'        => ['required', 'string', 'max:255'],
            'subtitle'     => ['nullable', 'string', 'max:255'],
            'series_name'  => ['nullable', 'string', 'max:150'],
            'publisher_id' => ['nullable', 'integer', 'exists:publishers,id'],
            'subject_code' => ['nullable', 'string', 'max:30'],
            'school_code'  => ['nullable', 'string', 'max:30'],
            'price'        => ['required', 'integer', 'min:0'],
            'default_discount_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status_code'  => ['required', 'string', 'max:30'],
            'author'       => ['nullable', 'string', 'max:150'],
            'spec'         => ['nullable', 'string', 'max:100'],
            'pub_date'     => ['nullable', 'date'],
            'edition'      => ['nullable', 'string', 'max:50'],
            'cover_path'   => ['nullable', 'string', 'max:255'],
            'source'       => ['nullable', 'string', 'max:20'],
        ]);
    }

    private function syncTargets(Book $book, Request $request): void
    {
        $grades    = (array) $request->input('grade_codes', []);
        $levels    = (array) $request->input('level_codes', []);
        $schools   = (array) $request->input('school_targets', []);
        $semesters = (array) $request->input('semester_codes', []);

        DB::table('book_targets')->where('book_id', $book->id)->delete();
        $now = now();
        $rows = [];
        foreach ($grades as $g) {
            $rows[] = ['book_id' => $book->id, 'target_type' => 'grade', 'code' => $g, 'created_at' => $now, 'updated_at' => $now];
        }
        foreach ($levels as $l) {
            $rows[] = ['book_id' => $book->id, 'target_type' => 'level', 'code' => $l, 'created_at' => $now, 'updated_at' => $now];
        }
        foreach ($schools as $s) {
            $rows[] = ['book_id' => $book->id, 'target_type' => 'school', 'code' => $s, 'created_at' => $now, 'updated_at' => $now];
        }
        foreach ($semesters as $sem) {
            $rows[] = ['book_id' => $book->id, 'target_type' => 'semester', 'code' => $sem, 'created_at' => $now, 'updated_at' => $now];
        }
        if ($rows) DB::table('book_targets')->insert($rows);
    }
}
