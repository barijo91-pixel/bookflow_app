<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DistributorScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * 영업자·총판이 판매 가능한 교재를 둘러보는 화면.
 *
 * 주문 화면(order/new)은 학원이 정해져야 들어갈 수 있어서, 담당 학원이 없는
 * 신규 영업자는 교재를 볼 방법이 없었다. 영업하러 나갈 때 정작 필요한 게 이건데.
 * 여기서는 학원 없이도 전체 교재를 보고, 학원을 고르면 그 학원 공급가까지 확인한다.
 */
class BookCatalogController extends Controller
{
    private function authorize(): User
    {
        $user = Auth::user();
        if (! $user || ! in_array($user->role_code, ['agent', 'distributor'], true)) {
            abort(403, '영업자 또는 총판만 접근 가능합니다.');
        }
        return $user;
    }

    public function index(Request $request)
    {
        $user = $this->authorize();

        // 담당(산하) 학원 — 고르면 그 학원 공급가를 계산해 보여준다
        $vendors = $user->role_code === 'agent'
            ? DB::table('agent_vendor_discounts as a')
                ->join('vendors as v', 'v.id', '=', 'a.vendor_id')
                ->where('a.agent_user_id', $user->id)->where('a.is_active', true)
                ->whereNull('v.deleted_at')->orderBy('v.name')
                ->get(['v.id', 'v.name'])
            : DB::table('vendors')->whereIn('id', DistributorScopeService::vendorIds($user->id))
                ->whereNull('deleted_at')->orderBy('name')->get(['id', 'name']);

        $vendorId = (int) $request->query('vendor_id');
        $selectedVendor = $vendors->firstWhere('id', $vendorId);

        // 선택한 학원 기준 할인율 (일반 할인율 + 도서별 할인율)
        $generalRate   = null;
        $bookDiscounts = collect();
        if ($selectedVendor && $user->role_code === 'agent') {
            $generalRate = DB::table('agent_vendor_discounts')
                ->where('agent_user_id', $user->id)->where('vendor_id', $selectedVendor->id)
                ->where('is_active', true)->value('discount_rate');
            $bookDiscounts = DB::table('agent_vendor_book_discounts')
                ->where('agent_user_id', $user->id)->where('vendor_id', $selectedVendor->id)
                ->where('is_active', true)->pluck('discount_rate', 'book_id');
        }

        $q         = trim((string) $request->query('q'));
        $school    = $request->query('school');
        $subject   = $request->query('subject');
        $grade     = $request->query('grade');
        $semester  = $request->query('semester');
        $publisher = $request->query('publisher');

        $query = DB::table('books as b')
            ->leftJoin('publishers as p', 'p.id', '=', 'b.publisher_id')
            ->whereNull('b.deleted_at')
            ->where('b.status_code', 'selling')
            ->select('b.id', 'b.isbn', 'b.title', 'b.subtitle', 'b.series_name', 'b.author',
                'b.price', 'b.school_code', 'b.subject_code', 'b.cover_path',
                'p.name as publisher_name');

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('b.title', 'like', "%{$q}%")
                  ->orWhere('b.isbn', 'like', "%{$q}%")
                  ->orWhere('b.series_name', 'like', "%{$q}%")
                  ->orWhere('b.author', 'like', "%{$q}%");
            });
        }
        if ($school)    $query->where('b.school_code', $school);
        if ($subject)   $query->where('b.subject_code', $subject);
        if ($publisher) $query->where('b.publisher_id', $publisher);

        $books = $query->orderBy('p.name')->orderBy('b.title')->paginate(60)->withQueryString();

        $filterOptions = [
            'school'    => DB::table('codes')->where('group_code', 'school')->orderBy('sort_order')->get(['code', 'name']),
            'subject'   => DB::table('codes')->where('group_code', 'subject')->orderBy('sort_order')->get(['code', 'name']),
            'publisher' => DB::table('publishers as p')
                ->whereIn('p.id', function ($sq) {
                    $sq->select('publisher_id')->from('books')
                       ->whereNull('deleted_at')->where('status_code', 'selling')->whereNotNull('publisher_id');
                })
                ->orderBy('p.name')->get(['p.id', 'p.name']),
        ];

        return view('public.mypage.books', [
            'user'           => $user,
            'books'          => $books,
            'vendors'        => $vendors,
            'selectedVendor' => $selectedVendor,
            'generalRate'    => $generalRate,
            'bookDiscounts'  => $bookDiscounts,
            'filterOptions'  => $filterOptions,
            'activeFilters'  => compact('q', 'school', 'subject', 'grade', 'semester', 'publisher'),
        ]);
    }
}
