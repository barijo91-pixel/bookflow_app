<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BookController;
use App\Http\Controllers\Admin\BookImportController;
use App\Http\Controllers\Admin\ClassController;
use App\Http\Controllers\Admin\CodeController;
use App\Http\Controllers\Admin\CodeGroupController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\RegionAdminController;
use App\Http\Controllers\Admin\RegionController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StockController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserImportController;
use App\Http\Controllers\Admin\VendorController;
use Illuminate\Support\Facades\Route;

// 공개 랜딩 페이지
Route::get('/', function () {
    return view('welcome');
})->name('home');

// 학부모 공유링크 (공개, 토큰 기반)
Route::get('/p/{token}', [\App\Http\Controllers\ParentShareController::class, 'show'])->name('parent.share');
// 학부모 결제 페이지 — 토큰 추측 방어 (분당 30회)
Route::get('/pay/{token}', [\App\Http\Controllers\PaymentRequestController::class, 'publicShow'])
    ->middleware('throttle:30,1')
    ->name('public.pay');
// Mock PG 결제 처리 (PortOne 미설정 시 fallback) — 분당 10회 제한
Route::post('/pay/{token}/mock-pay', [\App\Http\Controllers\PaymentRequestController::class, 'mockPay'])
    ->middleware('throttle:10,1')
    ->name('public.pay.mock');
// PortOne 실 PG 결제 완료 콜백 — 분당 30회 제한
Route::post('/pay/{token}/portone-complete', [\App\Http\Controllers\PaymentRequestController::class, 'portOneComplete'])
    ->middleware('throttle:30,1')
    ->name('public.pay.portone');

// SEO
Route::get('/sitemap.xml', [\App\Http\Controllers\SitemapController::class, 'index'])->name('sitemap');

// 공개 회원가입/로그인
Route::middleware('guest')->group(function () {
    Route::get('login',     [\App\Http\Controllers\PublicAuthController::class, 'showLogin'])->name('public.login');
    // 로그인/회원가입 POST — 브루트포스/스팸 방어 (분당 5회)
    Route::middleware('throttle:5,1')->group(function () {
        Route::post('login',    [\App\Http\Controllers\PublicAuthController::class, 'login'])->name('public.login.attempt');
        Route::post('register', [\App\Http\Controllers\PublicAuthController::class, 'register'])->name('public.register.attempt');
    });
    Route::get('register',  [\App\Http\Controllers\PublicAuthController::class, 'showRegister'])->name('public.register');
});
Route::get('register/done', [\App\Http\Controllers\PublicAuthController::class, 'registerDone'])->name('public.register.done');
Route::post('logout',       [\App\Http\Controllers\PublicAuthController::class, 'logout'])->name('public.logout');

// 마이페이지 (로그인 필요)
Route::middleware('auth')->group(function () {
    Route::get('mypage',          [\App\Http\Controllers\MyPageController::class, 'index'])->name('mypage');
    Route::get('mypage/profile',  [\App\Http\Controllers\MyPageController::class, 'showProfile'])->name('mypage.profile');
    Route::put('mypage/profile',  [\App\Http\Controllers\MyPageController::class, 'updateProfile'])->name('mypage.profile.update');
    Route::get('mypage/tax',      [\App\Http\Controllers\MyPageController::class, 'taxInfo'])->name('mypage.tax');
    Route::get('mypage/settlements', [\App\Http\Controllers\MyPageController::class, 'settlements'])->name('mypage.settlements');
    Route::get('mypage/income-simulator', [\App\Http\Controllers\MyPageController::class, 'incomeSimulator'])->name('mypage.income_simulator');
    // 비밀번호 변경 — 현재 비번 brute force 방어 (15분에 5회)
    Route::put('mypage/password', [\App\Http\Controllers\MyPageController::class, 'updatePassword'])
        ->middleware('throttle:5,15')
        ->name('mypage.password.update');

    // 비밀번호 강제 변경 (첫 로그인/관리자 초기화 후)
    Route::get('mypage/force-password-change',  [\App\Http\Controllers\MyPageController::class, 'showForcePasswordChange'])->name('mypage.force_password_change');
    Route::put('mypage/force-password-change',  [\App\Http\Controllers\MyPageController::class, 'submitForcePasswordChange'])
        ->middleware('throttle:5,15')
        ->name('mypage.force_password_change.submit');

    // 역할별 메뉴
    Route::prefix('mypage')->name('my.')->group(function () {
        // 공통 - 주문 목록/상세/액션
        Route::get('orders',                       [\App\Http\Controllers\MyPageController::class, 'ordersIndex'])->name('orders.index');
        Route::get('orders/{id}',                  [\App\Http\Controllers\MyPageController::class, 'showOrder'])->name('orders.show');
        Route::get('orders/{id}/edit',             [\App\Http\Controllers\MyPageController::class, 'editOrder'])->name('orders.edit');
        Route::put('orders/{id}',                  [\App\Http\Controllers\MyPageController::class, 'updateOrder'])->name('orders.update');
        Route::post('orders/{id}/transition',      [\App\Http\Controllers\MyPageController::class, 'transitionOrder'])->name('orders.transition');
        Route::post('orders/{id}/ship',            [\App\Http\Controllers\MyPageController::class, 'shipOrder'])->name('orders.ship');
        // 영업자: 직접배송 신청 (계획서 6-2장)
        Route::post('orders/{id}/direct-delivery', [\App\Http\Controllers\MyPageController::class, 'requestDirectDelivery'])->name('orders.direct_delivery');

        // 학원 — 학부모 결제 요청
        Route::get('orders/{id}/payment-requests/create',
            [\App\Http\Controllers\PaymentRequestController::class, 'create'])->name('orders.payment.create');
        Route::post('orders/{id}/payment-requests',
            [\App\Http\Controllers\PaymentRequestController::class, 'store'])->name('orders.payment.store');
        Route::get('classes/{classId}/students-with-parents',
            [\App\Http\Controllers\PaymentRequestController::class, 'studentsWithParents'])->name('classes.students_parents');

        // 총판 전용 - 재고 관리 (Phase B-6)
        Route::get('stocks',                [\App\Http\Controllers\MyPageController::class, 'stocksIndex'])->name('stocks.index');
        // 총판 재고 엑셀 일괄 등록
        Route::get('stocks/import',         [\App\Http\Controllers\Public\StockImportController::class, 'show'])->name('stocks.import.show');
        Route::get('stocks/import/template',[\App\Http\Controllers\Public\StockImportController::class, 'template'])->name('stocks.import.template');
        Route::post('stocks/import/preview',[\App\Http\Controllers\Public\StockImportController::class, 'preview'])->name('stocks.import.preview');
        Route::post('stocks/import/{jobId}/run', [\App\Http\Controllers\Public\StockImportController::class, 'run'])->name('stocks.import.run');
        Route::post('stocks',               [\App\Http\Controllers\MyPageController::class, 'stockStore'])->name('stocks.store');
        Route::put('stocks/{stockId}',      [\App\Http\Controllers\MyPageController::class, 'stockUpdate'])->name('stocks.update');
        Route::delete('stocks/{stockId}',   [\App\Http\Controllers\MyPageController::class, 'stockDestroy'])->name('stocks.destroy');
        Route::get('agents',     [\App\Http\Controllers\MyPageController::class, 'agentsIndex'])->name('agents.index');

        // 영업자 전용
        Route::get('vendors',    [\App\Http\Controllers\MyPageController::class, 'vendorsIndex'])->name('vendors.index');
        Route::get('vendors/create', [\App\Http\Controllers\Public\AgentVendorController::class, 'create'])->name('vendors.create');
        Route::post('vendors',       [\App\Http\Controllers\Public\AgentVendorController::class, 'store'])->name('vendors.store');
        Route::get('vendors/{vendor}', [\App\Http\Controllers\Public\AgentVendorController::class, 'show'])->name('vendors.show');
        Route::put('vendors/{vendor}', [\App\Http\Controllers\Public\AgentVendorController::class, 'update'])->name('vendors.update');
        Route::get('regions/sigungu', [\App\Http\Controllers\Admin\RegionController::class, 'sigungu'])->name('regions.sigungu');
        Route::get('discounts',  [\App\Http\Controllers\MyPageController::class, 'discountsIndex'])->name('discounts.index');
        Route::put('discounts/vendor/{avdId}',     [\App\Http\Controllers\MyPageController::class, 'discountVendorUpdate'])->name('discounts.vendor.update');
        Route::delete('discounts/vendor/{avdId}',  [\App\Http\Controllers\MyPageController::class, 'discountVendorDestroy'])->name('discounts.vendor.destroy');
        Route::post('discounts/book',              [\App\Http\Controllers\MyPageController::class, 'discountBookUpsert'])->name('discounts.book.upsert');
        Route::delete('discounts/book/{avbdId}',   [\App\Http\Controllers\MyPageController::class, 'discountBookDestroy'])->name('discounts.book.destroy');

        // 학원 전용 - 주문하기 + 장바구니
        Route::get('order/new',  [\App\Http\Controllers\MyPageController::class, 'orderNew'])->name('order_new');
        Route::post('cart/add',     [\App\Http\Controllers\MyPageController::class, 'cartAdd'])->name('cart.add');
        Route::post('cart/scan',    [\App\Http\Controllers\MyPageController::class, 'cartScanAdd'])->name('cart.scan');
        Route::post('cart/update',  [\App\Http\Controllers\MyPageController::class, 'cartUpdate'])->name('cart.update');
        Route::post('cart/remove',  [\App\Http\Controllers\MyPageController::class, 'cartRemove'])->name('cart.remove');
        Route::post('order/store',  [\App\Http\Controllers\MyPageController::class, 'storeOrder'])->name('order.store');

        // 학급/학생 (학원) — Phase B-8
        Route::get('classes',                 [\App\Http\Controllers\MyPageController::class, 'classesIndex'])->name('classes.index');
        Route::post('classes',                [\App\Http\Controllers\MyPageController::class, 'classesStore'])->name('classes.store');
        Route::get('classes/{id}',            [\App\Http\Controllers\MyPageController::class, 'classesShow'])->name('classes.show');
        Route::put('classes/{id}',            [\App\Http\Controllers\MyPageController::class, 'classesUpdate'])->name('classes.update');
        Route::delete('classes/{id}',         [\App\Http\Controllers\MyPageController::class, 'classesDestroy'])->name('classes.destroy');
        Route::post('classes/{id}/students',          [\App\Http\Controllers\MyPageController::class, 'classAttachStudent'])->name('classes.students.attach');
        Route::delete('classes/{id}/students/{sid}',  [\App\Http\Controllers\MyPageController::class, 'classDetachStudent'])->name('classes.students.detach');
        Route::post('classes/{id}/books',             [\App\Http\Controllers\MyPageController::class, 'classAttachBook'])->name('classes.books.attach');
        Route::delete('classes/{id}/books/{cbid}',    [\App\Http\Controllers\MyPageController::class, 'classDetachBook'])->name('classes.books.detach');
        Route::post('classes/{id}/share',             [\App\Http\Controllers\MyPageController::class, 'classCreateShareLink'])->name('classes.share');

        // 학생 엑셀 일괄 등록 (학원·영업자 공용 — 컨트롤러에서 권한 분기)
        Route::get('classes/{id}/students/import',
            [\App\Http\Controllers\Public\StudentImportController::class, 'show'])->name('classes.students.import.show');
        Route::get('classes/{id}/students/import/template',
            [\App\Http\Controllers\Public\StudentImportController::class, 'template'])->name('classes.students.import.template');
        Route::post('classes/{id}/students/import/preview',
            [\App\Http\Controllers\Public\StudentImportController::class, 'preview'])->name('classes.students.import.preview');
        Route::post('classes/{id}/students/import/{jobId}/run',
            [\App\Http\Controllers\Public\StudentImportController::class, 'run'])->name('classes.students.import.run');

        // 영업자 진입점 (학원·학급 선택)
        Route::get('student-import',
            [\App\Http\Controllers\Public\StudentImportController::class, 'agentSelect'])->name('agent.student.import');
    });
});

// 관리자
Route::prefix('admin')->name('admin.')->group(function () {
    // 로그인 (비인증 접근 허용)
    Route::middleware('guest')->group(function () {
        Route::get('login',  [AuthController::class, 'showLogin'])->name('login');
        // 관리자 로그인 POST — 브루트포스 방어 (분당 5회)
        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:5,1')
            ->name('login.attempt');
    });
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    // 관리자 전용
    Route::middleware(['auth', 'admin', 'admin.session.timeout'])->group(function () {
        Route::get('/', fn () => redirect()->route('admin.dashboard'));
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('operations-checklist', [DashboardController::class, 'operationsChecklist'])->name('operations_checklist');

        // 사용자
        Route::get('users',                  [UserController::class, 'index'])->name('users.index');
        Route::get('users/create',           [UserController::class, 'create'])->name('users.create');
        Route::post('users',                 [UserController::class, 'store'])->name('users.store');
        Route::get('users/pending',          [UserController::class, 'pending'])->name('users.pending');
        // 사용자 엑셀 일괄 등록
        Route::get('users/import',           [UserImportController::class, 'show'])->name('users.import.show');
        Route::get('users/import/template',  [UserImportController::class, 'template'])->name('users.import.template');
        Route::post('users/import/preview',  [UserImportController::class, 'preview'])->name('users.import.preview');
        Route::post('users/import/{jobId}/run', [UserImportController::class, 'run'])->name('users.import.run');
        Route::get('users/{user}',           [UserController::class, 'show'])->name('users.show');
        Route::put('users/{user}',           [UserController::class, 'update'])->name('users.update');
        Route::post('users/{user}/approve',  [UserController::class, 'approve'])->name('users.approve');
        Route::post('users/{user}/reject',   [UserController::class, 'reject'])->name('users.reject');
        Route::post('users/{user}/suspend',  [UserController::class, 'suspend'])->name('users.suspend');
        Route::post('users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset_password');
        Route::post('users/{user}/assign-distributor', [UserController::class, 'assignDistributor'])->name('users.assign_distributor');

        // 지역 (ajax + 관리)
        Route::get('regions/sigungu', [RegionController::class, 'sigungu'])->name('regions.sigungu');
        Route::get('regions',          [RegionAdminController::class, 'index'])->name('regions.index');
        Route::post('regions',         [RegionAdminController::class, 'store'])->name('regions.store');
        Route::put('regions/{id}',     [RegionAdminController::class, 'update'])->name('regions.update');
        Route::delete('regions/{id}',  [RegionAdminController::class, 'destroy'])->name('regions.destroy');

        // 사이트 설정
        Route::get('settings/{group?}',         [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings/{group}',          [SettingsController::class, 'update'])->name('settings.update');

        // 학급 / B2C
        Route::get('classes',                       [ClassController::class, 'index'])->name('classes.index');
        Route::get('classes/create',                [ClassController::class, 'create'])->name('classes.create');
        Route::post('classes',                      [ClassController::class, 'store'])->name('classes.store');
        Route::get('classes/{id}',                  [ClassController::class, 'show'])->name('classes.show');
        Route::put('classes/{id}',                  [ClassController::class, 'update'])->name('classes.update');
        Route::delete('classes/{id}',               [ClassController::class, 'destroy'])->name('classes.destroy');
        Route::post('classes/{id}/students',        [ClassController::class, 'attachStudent'])->name('classes.students.attach');
        Route::delete('classes/{id}/students/{sid}',[ClassController::class, 'detachStudent'])->name('classes.students.detach');
        Route::post('classes/{id}/books',           [ClassController::class, 'attachBook'])->name('classes.books.attach');
        Route::delete('classes/{id}/books/{cbid}',  [ClassController::class, 'detachBook'])->name('classes.books.detach');
        Route::post('classes/{id}/share',           [ClassController::class, 'createShareLink'])->name('classes.share');

        // 감사 로그
        Route::get('audit-logs',        [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('audit-logs/{id}',   [AuditLogController::class, 'show'])->name('audit-logs.show');

        // 알림
        Route::get('notifications/templates',       [NotificationController::class, 'templates'])->name('notifications.templates');
        Route::put('notifications/templates/{id}',  [NotificationController::class, 'updateTemplate'])->name('notifications.templates.update');
        Route::get('notifications/logs',            [NotificationController::class, 'logs'])->name('notifications.logs');

        // 주문
        Route::get('orders',                      [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}',              [OrderController::class, 'show'])->name('orders.show');
        Route::post('orders/{order}/transition',  [OrderController::class, 'transition'])->name('orders.transition');
        Route::post('orders/{order}/ship',        [OrderController::class, 'ship'])->name('orders.ship');

        // 재고
        Route::get('stocks',                    [StockController::class, 'index'])->name('stocks.index');
        Route::post('stocks',                   [StockController::class, 'store'])->name('stocks.store');
        Route::put('stocks/bulk',               [StockController::class, 'bulkUpdate'])->name('stocks.bulk-update');
        // 재고 엑셀 일괄 등록 (다중 총판)
        Route::get('stocks/import',             [\App\Http\Controllers\Admin\StockBulkImportController::class, 'show'])->name('stocks.import.show');
        Route::get('stocks/import/template',    [\App\Http\Controllers\Admin\StockBulkImportController::class, 'template'])->name('stocks.import.template');
        Route::post('stocks/import/preview',    [\App\Http\Controllers\Admin\StockBulkImportController::class, 'preview'])->name('stocks.import.preview');
        Route::post('stocks/import/{jobId}/run',[\App\Http\Controllers\Admin\StockBulkImportController::class, 'run'])->name('stocks.import.run');
        Route::put('stocks/{stockId}',          [StockController::class, 'update'])->name('stocks.update');
        Route::delete('stocks/{stockId}',       [StockController::class, 'destroy'])->name('stocks.destroy');

        // 도서
        Route::get('books',                     [BookController::class, 'index'])->name('books.index');
        Route::get('books/create',              [BookController::class, 'create'])->name('books.create');
        Route::post('books',                    [BookController::class, 'store'])->name('books.store');
        Route::get('books/aladin/lookup',       [BookController::class, 'aladinLookup'])->name('books.aladin.lookup');
        Route::get('books/aladin/search',       [BookController::class, 'aladinSearch'])->name('books.aladin.search');
        // 엑셀 일괄 등록
        Route::get('books/import',              [BookImportController::class, 'show'])->name('books.import.show');
        Route::get('books/import/template',     [BookImportController::class, 'template'])->name('books.import.template');
        Route::post('books/import/preview',     [BookImportController::class, 'preview'])->name('books.import.preview');
        Route::post('books/import/{jobId}/run', [BookImportController::class, 'run'])->name('books.import.run');
        // 표지 이미지 ZIP 일괄 업로드
        Route::get('books/covers',              [\App\Http\Controllers\Admin\CoverImportController::class, 'show'])->name('books.covers.show');
        Route::post('books/covers/upload',      [\App\Http\Controllers\Admin\CoverImportController::class, 'upload'])->name('books.covers.upload');
        Route::get('books/{book}',              [BookController::class, 'show'])->name('books.show');
        Route::put('books/{book}',              [BookController::class, 'update'])->name('books.update');
        Route::delete('books/{book}',           [BookController::class, 'destroy'])->name('books.destroy');

        // 거래처(학원)
        Route::get('vendors',                   [VendorController::class, 'index'])->name('vendors.index');
        Route::get('vendors/create',            [VendorController::class, 'create'])->name('vendors.create');
        Route::post('vendors',                  [VendorController::class, 'store'])->name('vendors.store');
        Route::get('vendors/{vendor}',          [VendorController::class, 'show'])->name('vendors.show');
        Route::put('vendors/{vendor}',          [VendorController::class, 'update'])->name('vendors.update');
        Route::delete('vendors/{vendor}',       [VendorController::class, 'destroy'])->name('vendors.destroy');
        // 담당자 / 영업자 매핑
        Route::post('vendors/{vendor}/staffs',                    [VendorController::class, 'attachStaff'])->name('vendors.staffs.attach');
        Route::delete('vendors/{vendor}/staffs/{linkId}',         [VendorController::class, 'detachStaff'])->name('vendors.staffs.detach');
        Route::post('vendors/{vendor}/agents',                    [VendorController::class, 'attachAgent'])->name('vendors.agents.attach');
        Route::put('vendors/{vendor}/agents/{linkId}',            [VendorController::class, 'updateAgentRate'])->name('vendors.agents.update');
        Route::delete('vendors/{vendor}/agents/{linkId}',         [VendorController::class, 'detachAgent'])->name('vendors.agents.detach');

        // 코드 그룹
        Route::get('code-groups',                          [CodeGroupController::class, 'index'])->name('code-groups.index');
        Route::post('code-groups',                         [CodeGroupController::class, 'store'])->name('code-groups.store');
        Route::put('code-groups/{group_code}',             [CodeGroupController::class, 'update'])->name('code-groups.update');
        Route::delete('code-groups/{group_code}',          [CodeGroupController::class, 'destroy'])->name('code-groups.destroy');

        // 코드 (그룹 하위)
        Route::get('code-groups/{group_code}/codes',          [CodeController::class, 'index'])->name('codes.index');
        Route::post('code-groups/{group_code}/codes',         [CodeController::class, 'store'])->name('codes.store');
        Route::put('code-groups/{group_code}/codes/{id}',     [CodeController::class, 'update'])->name('codes.update');
        Route::delete('code-groups/{group_code}/codes/{id}',  [CodeController::class, 'destroy'])->name('codes.destroy');

        // 정산 시뮬레이션 (계획서 7장 — PG 실연동 전 계산 검증용)
        Route::get('settlement/simulator',     [\App\Http\Controllers\Admin\SettlementController::class, 'simulator'])->name('settlement.simulator');
        Route::get('settlement/order/{order}', [\App\Http\Controllers\Admin\SettlementController::class, 'orderPreview'])->name('settlement.order_preview');
        // 정산 레코드 (PG 결제 완료 후 자동 생성)
        Route::get('settlement/records',                  [\App\Http\Controllers\Admin\SettlementController::class, 'records'])->name('settlement.records');
        Route::get('settlement/records/{id}',             [\App\Http\Controllers\Admin\SettlementController::class, 'recordShow'])->name('settlement.record_show');
        Route::post('settlement/records/{id}/mark-paid',  [\App\Http\Controllers\Admin\SettlementController::class, 'recordMarkPaid'])->name('settlement.record_mark_paid');
    });
});
