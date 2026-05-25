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
use App\Http\Controllers\Admin\VendorController;
use Illuminate\Support\Facades\Route;

// 공개 랜딩 페이지
Route::get('/', function () {
    return view('welcome');
})->name('home');

// 학부모 공유링크 (공개, 토큰 기반)
Route::get('/p/{token}', [\App\Http\Controllers\ParentShareController::class, 'show'])->name('parent.share');

// SEO
Route::get('/sitemap.xml', [\App\Http\Controllers\SitemapController::class, 'index'])->name('sitemap');

// 공개 회원가입/로그인
Route::middleware('guest')->group(function () {
    Route::get('login',     [\App\Http\Controllers\PublicAuthController::class, 'showLogin'])->name('public.login');
    Route::post('login',    [\App\Http\Controllers\PublicAuthController::class, 'login'])->name('public.login.attempt');
    Route::get('register',  [\App\Http\Controllers\PublicAuthController::class, 'showRegister'])->name('public.register');
    Route::post('register', [\App\Http\Controllers\PublicAuthController::class, 'register'])->name('public.register.attempt');
});
Route::get('register/done', [\App\Http\Controllers\PublicAuthController::class, 'registerDone'])->name('public.register.done');
Route::post('logout',       [\App\Http\Controllers\PublicAuthController::class, 'logout'])->name('public.logout');

// 마이페이지 (로그인 필요)
Route::middleware('auth')->group(function () {
    Route::get('mypage',          [\App\Http\Controllers\MyPageController::class, 'index'])->name('mypage');
    Route::get('mypage/profile',  [\App\Http\Controllers\MyPageController::class, 'showProfile'])->name('mypage.profile');
    Route::put('mypage/profile',  [\App\Http\Controllers\MyPageController::class, 'updateProfile'])->name('mypage.profile.update');
    Route::put('mypage/password', [\App\Http\Controllers\MyPageController::class, 'updatePassword'])->name('mypage.password.update');

    // 비밀번호 강제 변경 (첫 로그인/관리자 초기화 후)
    Route::get('mypage/force-password-change',  [\App\Http\Controllers\MyPageController::class, 'showForcePasswordChange'])->name('mypage.force_password_change');
    Route::put('mypage/force-password-change',  [\App\Http\Controllers\MyPageController::class, 'submitForcePasswordChange'])->name('mypage.force_password_change.submit');

    // 역할별 메뉴
    Route::prefix('mypage')->name('my.')->group(function () {
        // 공통 - 주문 목록/상세/액션
        Route::get('orders',                       [\App\Http\Controllers\MyPageController::class, 'ordersIndex'])->name('orders.index');
        Route::get('orders/{id}',                  [\App\Http\Controllers\MyPageController::class, 'showOrder'])->name('orders.show');
        Route::post('orders/{id}/transition',      [\App\Http\Controllers\MyPageController::class, 'transitionOrder'])->name('orders.transition');
        Route::post('orders/{id}/ship',            [\App\Http\Controllers\MyPageController::class, 'shipOrder'])->name('orders.ship');

        // 총판 전용
        Route::get('stocks',     [\App\Http\Controllers\MyPageController::class, 'stocksIndex'])->name('stocks.index');
        Route::get('agents',     [\App\Http\Controllers\MyPageController::class, 'agentsIndex'])->name('agents.index');

        // 영업자 전용
        Route::get('vendors',    [\App\Http\Controllers\MyPageController::class, 'vendorsIndex'])->name('vendors.index');
        Route::get('discounts',  [\App\Http\Controllers\MyPageController::class, 'discountsIndex'])->name('discounts.index');

        // 학원 전용
        Route::get('order/new',  [\App\Http\Controllers\MyPageController::class, 'orderNew'])->name('order_new');
        Route::get('classes',    [\App\Http\Controllers\MyPageController::class, 'classesIndex'])->name('classes.index');
    });
});

// 관리자
Route::prefix('admin')->name('admin.')->group(function () {
    // 로그인 (비인증 접근 허용)
    Route::middleware('guest')->group(function () {
        Route::get('login',  [AuthController::class, 'showLogin'])->name('login');
        Route::post('login', [AuthController::class, 'login'])->name('login.attempt');
    });
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    // 관리자 전용
    Route::middleware(['auth', 'admin', 'admin.session.timeout'])->group(function () {
        Route::get('/', fn () => redirect()->route('admin.dashboard'));
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // 사용자
        Route::get('users',                  [UserController::class, 'index'])->name('users.index');
        Route::get('users/create',           [UserController::class, 'create'])->name('users.create');
        Route::post('users',                 [UserController::class, 'store'])->name('users.store');
        Route::get('users/pending',          [UserController::class, 'pending'])->name('users.pending');
        Route::get('users/{user}',           [UserController::class, 'show'])->name('users.show');
        Route::put('users/{user}',           [UserController::class, 'update'])->name('users.update');
        Route::post('users/{user}/approve',  [UserController::class, 'approve'])->name('users.approve');
        Route::post('users/{user}/reject',   [UserController::class, 'reject'])->name('users.reject');
        Route::post('users/{user}/suspend',  [UserController::class, 'suspend'])->name('users.suspend');
        Route::post('users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset_password');

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
    });
});
