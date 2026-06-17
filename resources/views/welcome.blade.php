<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">
    <title>{{ setting('meta_title', 'BookSys — 교재 도매 유통 전문 플랫폼') }}</title>
    <meta name="description" content="{{ setting('meta_description', '총판·영업자·학원·학부모를 연결하는 교재 도매 유통 올인원 솔루션. 전화·카카오톡으로 비효율적이던 영어 교재 유통을 디지털화합니다.') }}">
    <meta name="keywords" content="{{ setting('meta_keywords', '교재,도매,유통,학원,영어교재,총판,영업자') }}">

    {{-- Open Graph (카카오톡/페이스북) --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="BookSys">
    <meta property="og:title" content="{{ setting('meta_title', 'BookSys — 교재 도매 유통 전문 플랫폼') }}">
    <meta property="og:description" content="{{ setting('meta_description', '총판·영업자·학원·학부모를 연결하는 교재 도매 유통 올인원 솔루션') }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:locale" content="ko_KR">

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{{ setting('meta_title', 'BookSys') }}">
    <meta name="twitter:description" content="{{ setting('meta_description', '교재 도매 유통 전문 플랫폼') }}">

    {{-- JSON-LD Organization (@context, @type은 Blade와 충돌 → @@로 escape) --}}
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@@type": "Organization",
        "name": "{{ setting('company_name', 'e-Learn') }}",
        "url": "{{ url('/') }}",
        "description": "BookSys — 교재 도매 유통 전문 플랫폼"
    }
    </script>

    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --navy: #1f3a5f;
            --navy-dark: #15294a;
            --navy-soft: #eaf0fa;
            --text: #212529;
            --muted: #6c757d;
        }
        body { font-family: 'Noto Sans KR', sans-serif; color: var(--text); }
        .navy { color: var(--navy); }
        .bg-navy { background: var(--navy); color: #fff; }
        .bg-navy-soft { background: var(--navy-soft); }
        .btn-navy { background: var(--navy); border-color: var(--navy); color: #fff; }
        .btn-navy:hover, .btn-navy:focus { background: var(--navy-dark); border-color: var(--navy-dark); color: #fff; }
        .btn-outline-navy { color: var(--navy); border-color: var(--navy); }
        .btn-outline-navy:hover { background: var(--navy); color: #fff; }

        .topbar { background: #fff; border-bottom: 1px solid #e6e9ef; }
        .topbar .brand { color: var(--navy); font-weight: 700; font-size: 1.3rem; text-decoration: none; }
        .topbar .brand i { margin-right: .4rem; }

        .hero {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-dark) 100%);
            color: #fff;
            padding: 5rem 1rem;
        }
        .hero h1 { font-size: 2.6rem; font-weight: 900; letter-spacing: -.02em; word-break: keep-all; }
        .hero .lead { font-size: 1.15rem; opacity: .92; }
        .hero .badge-tag { background: rgba(255,255,255,.15); color: #fff; padding: .35rem .8rem; border-radius: 99px; font-size: .85rem; font-weight: 500; }

        section { padding: 4rem 1rem; }
        .section-title { font-size: 1.7rem; font-weight: 700; color: var(--navy); text-align: center; margin-bottom: .6rem; }
        .section-sub { color: var(--muted); text-align: center; margin-bottom: 3rem; }

        .role-card {
            background: #fff;
            border: 1px solid #e6e9ef;
            border-radius: 14px;
            padding: 1.8rem 1.4rem;
            height: 100%;
            transition: transform .2s, box-shadow .2s;
        }
        .role-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(31,58,95,.1); }
        .role-card .icon-wrap {
            width: 56px; height: 56px; border-radius: 12px;
            background: var(--navy-soft); color: var(--navy);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.6rem; margin-bottom: 1rem;
        }
        .role-card h3 { font-size: 1.1rem; font-weight: 700; color: var(--navy); margin-bottom: .4rem; }
        .role-card p { color: var(--muted); font-size: .92rem; margin-bottom: 0; line-height: 1.6; }

        .feature-item { display: flex; gap: 1rem; margin-bottom: 2rem; }
        .feature-item .icon-wrap {
            flex-shrink: 0;
            width: 48px; height: 48px; border-radius: 10px;
            background: var(--navy); color: #fff;
            display: flex; align-items: center; justify-content: center; font-size: 1.4rem;
        }
        .feature-item h4 { font-size: 1.05rem; font-weight: 700; color: var(--navy); margin-bottom: .3rem; }
        .feature-item p { color: var(--muted); font-size: .92rem; margin-bottom: 0; line-height: 1.55; }

        .step-item { display: flex; gap: 1.2rem; padding: 1.4rem 0; border-bottom: 1px solid #f0f2f5; }
        .step-item:last-child { border-bottom: none; }
        .step-num {
            flex-shrink: 0;
            width: 40px; height: 40px; border-radius: 50%;
            background: var(--navy); color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 1.1rem;
        }
        .step-item h5 { font-size: 1.05rem; font-weight: 700; color: var(--navy); margin-bottom: .2rem; }
        .step-item p { color: var(--muted); font-size: .92rem; margin-bottom: 0; }

        footer {
            background: #f6f7fb;
            padding: 3rem 1rem 2rem;
            border-top: 1px solid #e6e9ef;
            color: var(--muted);
            font-size: .9rem;
        }
        footer h6 { color: var(--navy); font-weight: 700; margin-bottom: .8rem; font-size: .95rem; }
        footer a { color: var(--muted); text-decoration: none; }
        footer a:hover { color: var(--navy); }
        .copyright {
            margin-top: 2rem; padding-top: 1.5rem;
            border-top: 1px solid #e6e9ef;
            text-align: center; font-size: .85rem;
        }

        @media (max-width: 767.98px) {
            .hero { padding: 3rem 1rem; }
            .hero h1 { font-size: 1.6rem; line-height: 1.35; }
            .hero .lead { font-size: 1rem; }
            section { padding: 3rem 1rem; }
        }
        @media (max-width: 400px) {
            .hero h1 { font-size: 1.35rem; }
        }
    </style>
    @include('partials.pwa_meta')
</head>
<body>

{{-- Top bar --}}
<header class="topbar">
    <div class="container d-flex align-items-center justify-content-between py-3">
        <a href="/" class="brand">
            <i class="bi bi-book-half"></i>BookSys
        </a>
        <nav class="d-flex align-items-center gap-2">
            @auth
                @if(auth()->user()->role_code === 'admin')
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-navy btn-sm">
                        <i class="bi bi-speedometer2"></i> 관리자 콘솔
                    </a>
                @else
                    <a href="{{ route('mypage') }}" class="btn btn-outline-navy btn-sm">
                        <i class="bi bi-person"></i> 마이페이지
                    </a>
                @endif
                <form method="POST" action="{{ route('public.logout') }}" class="m-0">
                    @csrf
                    <button class="btn btn-sm btn-outline-secondary">로그아웃</button>
                </form>
            @else
                <a href="{{ route('public.login') }}" class="btn btn-outline-navy btn-sm">
                    <i class="bi bi-box-arrow-in-right"></i> 로그인
                </a>
                <a href="{{ route('public.register') }}" class="btn btn-navy btn-sm">
                    <i class="bi bi-person-plus"></i> 가입
                </a>
            @endauth
        </nav>
    </div>
</header>

{{-- Hero --}}
<div class="hero">
    <div class="container text-center">
        <span class="badge-tag mb-3 d-inline-block">교재 도매 유통 전문 플랫폼</span>
        <h1 class="mb-3">총판·영업자·학원·학부모<br>모두를 연결하는 올인원 솔루션</h1>
        <p class="lead mb-4">전화와 카카오톡으로 비효율적이던 영어 교재 유통.<br>이제 BookSys에서 디지털로 간편하게.</p>
        <div class="d-flex gap-2 justify-content-center flex-wrap">
            @auth
                @if(auth()->user()->role_code === 'admin')
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-light btn-lg px-4">
                        <i class="bi bi-speedometer2"></i> 관리자 콘솔
                    </a>
                @else
                    <a href="{{ route('mypage') }}" class="btn btn-light btn-lg px-4">
                        <i class="bi bi-person"></i> 마이페이지
                    </a>
                @endif
            @else
                <a href="{{ route('public.register') }}" class="btn btn-light btn-lg px-4">
                    <i class="bi bi-person-plus"></i> 이용하기
                </a>
                <a href="{{ route('public.login') }}" class="btn btn-outline-light btn-lg px-4">
                    <i class="bi bi-box-arrow-in-right"></i> 로그인
                </a>
            @endauth
            @if(setting('app_download_active') === '1' && (setting('app_download_android_url') || setting('app_download_ios_url')))
                <a href="#download" class="btn btn-outline-light btn-lg px-4">
                    <i class="bi bi-phone"></i> 앱 다운로드
                </a>
            @endif
        </div>
    </div>
</div>

{{-- 5단계 사용자 --}}
<section>
    <div class="container">
        <h2 class="section-title">누가 사용하나요?</h2>
        <p class="section-sub">총판부터 학부모까지 — 교재 유통의 모든 단계를 하나의 플랫폼에서</p>
        <div class="row g-3">
            <div class="col-6 col-lg-3">
                <div class="role-card">
                    <div class="icon-wrap"><i class="bi bi-truck"></i></div>
                    <h3>총판 (출판사 / 도매 공급자)</h3>
                    <p>플랫폼 최상위 공급자. 하위 영업자 네트워크 관리, 주문 접수 및 물류 출고를 한 화면에서.</p>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="role-card">
                    <div class="icon-wrap"><i class="bi bi-person-badge"></i></div>
                    <h3>영업자 (1인 사입 / 프리랜서)</h3>
                    <p>관리 학원을 내 소속으로 등록하고 학원별·교재별 할인율을 자유롭게 설정. 모바일 앱에서 주문 확정.</p>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="role-card">
                    <div class="icon-wrap"><i class="bi bi-building"></i></div>
                    <h3>학원 (학원장 / 담당자)</h3>
                    <p>도서 검색·바코드 스캔으로 주문. 계약된 할인율 자동 적용. 학급 편성과 학부모 교재 안내까지 한 번에.</p>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="role-card">
                    <div class="icon-wrap"><i class="bi bi-mortarboard"></i></div>
                    <h3>학부모 (B2C)</h3>
                    <p>학원에서 보낸 공유링크로 진입. 앱 설치 없이 웹뷰에서 교재 확인하고 간편하게 결제.</p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- 핵심 기능 --}}
<section class="bg-navy-soft">
    <div class="container">
        <h2 class="section-title">핵심 기능</h2>
        <p class="section-sub">현장의 페인 포인트를 정확히 해결합니다</p>
        <div class="row g-4">
            <div class="col-md-6">
                <div class="feature-item">
                    <div class="icon-wrap"><i class="bi bi-upc-scan"></i></div>
                    <div>
                        <h4>바코드 스캔 주문</h4>
                        <p>학원이 실제 교재 ISBN을 모바일 카메라로 스캔하면 그대로 주문서에. 수기 입력의 오타·오발주가 사라집니다.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="feature-item">
                    <div class="icon-wrap"><i class="bi bi-percent"></i></div>
                    <div>
                        <h4>학원별·교재별 할인율</h4>
                        <p>영업자가 학원별 기본 할인율을 설정하고, 특정 교재는 별도 할인율로 오버라이드. 주문 시 자동 계산.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="feature-item">
                    <div class="icon-wrap"><i class="bi bi-chat-square-text"></i></div>
                    <div>
                        <h4>카카오 알림톡 자동 발송</h4>
                        <p>주문 접수·확정·출고·배송 모든 단계에서 관련자에게 알림톡 자동 발송. 실패 시 SMS 자동 폴백.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="feature-item">
                    <div class="icon-wrap"><i class="bi bi-link-45deg"></i></div>
                    <div>
                        <h4>학부모 공유링크 (B2C)</h4>
                        <p>학원이 학급별 교재 목록을 편성하면 학부모에게 토큰 링크 발송. 앱 설치 불필요한 웹뷰.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="feature-item">
                    <div class="icon-wrap"><i class="bi bi-file-earmark-excel"></i></div>
                    <div>
                        <h4>도서 엑셀 일괄 등록</h4>
                        <p>출판사가 제공한 신간 목록 엑셀을 그대로 업로드. 알라딘 API로 부족한 정보 자동 보강.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="feature-item">
                    <div class="icon-wrap"><i class="bi bi-graph-up"></i></div>
                    <div>
                        <h4>총판별 재고 관리</h4>
                        <p>도서×총판 매트릭스로 재고 관리. 안전재고 임계값 자동 알림. 영업자 주문 시 재고 있는 총판으로 자동 라우팅.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- 주문 흐름 --}}
<section>
    <div class="container" style="max-width: 800px;">
        <h2 class="section-title">주문 흐름</h2>
        <p class="section-sub">학원 주문부터 학부모 결제까지 — 한눈에 추적</p>
        <div>
            <div class="step-item">
                <div class="step-num">1</div>
                <div>
                    <h5>학원이 모바일 앱에서 도서 주문</h5>
                    <p>바코드 스캔 또는 검색으로 도서 담기 → 자동 적용된 할인율 확인 → 주문 확정</p>
                </div>
            </div>
            <div class="step-item">
                <div class="step-num">2</div>
                <div>
                    <h5>영업자에게 실시간 알림</h5>
                    <p>담당 영업자 앱으로 푸시·알림톡 발송 → 영업자 확정 → 총판에게 자동 전달</p>
                </div>
            </div>
            <div class="step-item">
                <div class="step-num">3</div>
                <div>
                    <h5>총판이 패킹·출고</h5>
                    <p>총판 화면에서 송장 입력 → 학원에게 출고 알림톡 자동 발송 → 배송 현황 실시간 업데이트</p>
                </div>
            </div>
            <div class="step-item">
                <div class="step-num">4</div>
                <div>
                    <h5>학원이 학부모에게 안내 (B2C)</h5>
                    <p>학급별 교재 편성 → 학부모에게 공유링크 발송 → 학부모는 웹뷰에서 부분 선택 결제</p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- 앱 다운로드 --}}
@if(setting('app_download_active') === '1')
    @php
        $androidUrl = setting('app_download_android_url');
        $iosUrl = setting('app_download_ios_url');
        $androidVer = setting('app_download_android_version');
        $iosVer = setting('app_download_ios_version');
    @endphp
    <section id="download" style="background: #fff; padding: 4rem 1rem;">
        <div class="container" style="max-width: 720px;">
            <h2 class="section-title">{{ setting('app_download_label', 'BookSys 모바일 앱') }}</h2>
            <p class="section-sub">{{ setting('app_download_description', '바코드 스캔 주문, 푸시 알림 등 모바일에서만 가능한 기능을 사용하세요.') }}</p>

            <div class="row g-3 justify-content-center mt-4">
                @if($androidUrl)
                    <div class="col-md-5">
                        <a href="{{ $androidUrl }}" class="d-block text-decoration-none"
                           target="_blank" rel="noopener" download>
                            <div style="background: #1f3a5f; color: #fff; border-radius: 14px; padding: 1.4rem 1.6rem; display:flex; align-items:center; gap: 1rem; transition: transform .2s;">
                                <i class="bi bi-android2" style="font-size: 2.4rem;"></i>
                                <div class="flex-grow-1">
                                    <div style="font-size:.85rem; opacity:.85;">Android 다운로드</div>
                                    <div style="font-size:1.2rem; font-weight: 700;">.APK 설치</div>
                                    @if($androidVer)
                                        <div style="font-size:.8rem; opacity:.7;">v{{ $androidVer }}</div>
                                    @endif
                                </div>
                                <i class="bi bi-download" style="font-size: 1.4rem;"></i>
                            </div>
                        </a>
                    </div>
                @endif

                @if($iosUrl)
                    <div class="col-md-5">
                        <a href="{{ $iosUrl }}" class="d-block text-decoration-none"
                           target="_blank" rel="noopener">
                            <div style="background: #15294a; color: #fff; border-radius: 14px; padding: 1.4rem 1.6rem; display:flex; align-items:center; gap: 1rem;">
                                <i class="bi bi-apple" style="font-size: 2.4rem;"></i>
                                <div class="flex-grow-1">
                                    <div style="font-size:.85rem; opacity:.85;">iOS 다운로드</div>
                                    <div style="font-size:1.2rem; font-weight: 700;">App Store</div>
                                    @if($iosVer)
                                        <div style="font-size:.8rem; opacity:.7;">v{{ $iosVer }}</div>
                                    @endif
                                </div>
                                <i class="bi bi-box-arrow-up-right" style="font-size: 1.4rem;"></i>
                            </div>
                        </a>
                    </div>
                @endif

                @if(! $androidUrl && ! $iosUrl)
                    <div class="col-md-10 text-center">
                        <div style="background: #f6f7fb; border-radius: 14px; padding: 2rem;">
                            <i class="bi bi-hourglass-split text-muted" style="font-size: 2rem;"></i>
                            <p class="text-muted mt-2 mb-0">앱이 곧 출시됩니다. 잠시만 기다려주세요.</p>
                        </div>
                    </div>
                @endif
            </div>

            @if($androidUrl)
                <div class="text-center mt-4">
                    <small class="text-muted">
                        <i class="bi bi-info-circle"></i>
                        Android는 설정 → 보안 → "출처를 알 수 없는 앱" 허용 후 설치하실 수 있습니다.
                    </small>
                </div>
            @endif
        </div>
    </section>
@endif

{{-- CTA --}}
<section id="contact" class="bg-navy">
    <div class="container text-center">
        <h2 style="color:#fff; font-weight:700; margin-bottom: .6rem;">도입을 고려 중이신가요?</h2>
        <p style="opacity:.9; margin-bottom: 2rem;">총판/영업자/학원 누구나 — 간단한 상담으로 시작할 수 있습니다.</p>
        <div class="d-flex gap-2 justify-content-center flex-wrap">
            <a href="mailto:contact@bookflow.io" class="btn btn-light btn-lg px-4">
                <i class="bi bi-envelope"></i> 이메일 문의
            </a>
            <a href="{{ route('public.login') }}" class="btn btn-outline-light btn-lg px-4">
                <i class="bi bi-box-arrow-in-right"></i> 로그인
            </a>
        </div>
    </div>
</section>

{{-- Footer --}}
<footer>
    <div class="container">
        <div class="row g-3">
            <div class="col-md-4">
                <h6><i class="bi bi-book-half"></i> BookSys</h6>
                <p class="mb-0">교재 도매 유통 전문 플랫폼<br>
                    <small>Powered by e-Learn</small>
                </p>
            </div>
            <div class="col-md-4">
                <h6>서비스</h6>
                <ul class="list-unstyled mb-0">
                    <li><a href="#">총판 시스템</a></li>
                    <li><a href="#">영업자 모바일 앱</a></li>
                    <li><a href="#">학원 모바일 앱</a></li>
                    <li><a href="#">학부모 웹뷰</a></li>
                </ul>
            </div>
            <div class="col-md-4">
                <h6>회사 정보</h6>
                <p class="mb-1">{{ setting('company_name', 'e-Learn') }}</p>
                @if(setting('representative'))<p class="mb-1">대표 {{ setting('representative') }}</p>@endif
                @if(setting('business_no'))<p class="mb-1">사업자등록번호 {{ setting('business_no') }}</p>@endif
                @if(setting('company_address'))<p class="mb-1">{{ setting('company_address') }}</p>@endif
                @if(setting('company_phone'))<p class="mb-1"><i class="bi bi-telephone"></i> {{ setting('company_phone') }}</p>@endif
                @if(setting('company_email'))<p class="mb-1"><i class="bi bi-envelope"></i> {{ setting('company_email') }}</p>@endif
            </div>
        </div>
        <div class="copyright">
            &copy; {{ date('Y') }} BookSys · All rights reserved.
        </div>
    </div>
</footer>

</body>
</html>
