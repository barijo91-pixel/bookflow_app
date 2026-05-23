# BookFlow — 교재 도매 유통 플랫폼 (e-Learn)

> 이 문서는 새 세션에서도 작업을 이어가기 위한 프로젝트 안내서입니다.
> 작업 방법을 매번 다시 묻지 않도록 여기에 정리되어 있습니다.

## 프로젝트 개요

**BookFlow** — IT 비즈니스 회사 **"e-Learn"**이 운영하는 교재 도매 유통 전문 플랫폼.
영어 교재(유치·초등 중심)의 비효율적인 전화·카카오톡 유통을 디지털화하는 올인원 솔루션.

### 사용자 계층 (5단계)
```
시스템관리자 > 총판 > 영업자 > 학원 > 학부모
            (출판사    (1인 사입자  (학원장,   (B2C 구매자,
             도매공급)   프리랜서)    담당자)    웹뷰만 사용)
```

### 핵심 비즈니스 흐름
1. **B2B 주문**: 학원 앱에서 도서 검색·바코드 스캔 → 영업자 확정 → 총판 접수 → 출고/배송
2. **B2C 확장**: 학원이 학급 교재 편성 → 학부모에게 공유링크 발송 → 웹뷰에서 부분 결제 (1단계는 마스터 등록만)
3. **할인율**: 영업자×학원 기본 할인율 → 영업자×학원×교재 오버라이드 → 도서 자체 정가
4. **알림**: 알리고 알림톡 + SMS + FCM 푸시 + 이메일 (8개 자동 발송 이벤트)

### 작업 방향 (사용자 핵심 요구)
- 추측 금지, 모호하면 반드시 질문
- 이모지 사용 금지 (Bootstrap Icons만)
- 무채색 + 단일 액센트 (딥 네이비 `#1f3a5f`)
- 단계별 컨펌 후 진행
- 결제·정산·세금계산서는 후속 단계 (1단계 제외)

## 기술 스택

- **PHP 8.3.30** (Laragon `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`) — **PHP 8.3 필수** (Laravel 13 강제 요구사항). BookFlow 자체 코드는 8.3 전용 문법 없음.
- **Laravel 13.x** (현재 13.8) + Sanctum (모바일 토큰)
- **MySQL 8.4.3** (DB명: `bookflow`, user `root`, password 없음)
- **프론트**: Bootstrap 5.3 + Bootstrap Icons 1.11 (CDN), Google Fonts(Noto Sans KR)
- **모바일 앱**: Flutter (추후 별도 프로젝트 — 영업자/학원용)
- **학부모 웹뷰**: Laravel Blade + Bootstrap (별도 SPA X)
- **환경**: Windows 11 + Laragon. 작업 경로 `C:\laragon\www\bookflow`

## 로컬 실행

```bash
# 개발 서버 (포트 8778, 이런 e-learn 8777과 분리)
C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe artisan serve --host=127.0.0.1 --port=8778
```

- Laragon 사용 시 가상호스트 `http://bookflow.test` 도 접근 가능 (Laragon Reload 필요)
- Composer: `C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe C:/laragon/bin/composer/composer.phar ...`

## 관리자 계정 (시드)

- URL: `/admin/login`
- **로그인 ID 시스템**: 이메일 형식이 아닌 일반 아이디 (영문+숫자 6~50자)
- 운영 admin (ProductionSeeder): `admin01` / `admin1234` (첫 로그인 시 비번 변경 강제)
- 데모 계정 8개 (DemoAccountSeeder, 전부 비번 `1234` — 약한 비번 검사 명령 대상)
  - `admin01` (시스템관리자, super) — 옛 `admin@bookflow.local`
  - `distA01`, `distB01` (총판 ×2)
  - `agent01`, `agent02` (영업자 ×2)
  - `academy01`, `academy02`, `academy03` (학원 ×3)
- 기존 이메일은 옵션 컬럼(`users.email`, nullable)으로 유지 — 알림/연락처용

## 보안 정책

- **비밀번호**: 최소 8자 + 영문 + 숫자 필수 (관리자 비번 변경 시에도 동일 적용)
- **로그인 시도 제한**: 5회 실패 → 60초 잠금 (IP + 아이디 기준)
- **첫 로그인 시 비번 변경 강제**: `users.password_change_required = true` 인 사용자는 `/mypage/force-password-change` 로 자동 이동
- **관리자 세션 타임아웃**: 60분 비활성 시 자동 로그아웃
- **약한 비번 일괄 검사**: `php artisan security:flag-weak-passwords` (1234 등 알려진 약한 비번 사용자에게 변경 강제 플래그 설정)
- **이전 로그인 시각/IP**: 대시보드 + 마이페이지에 표시 (이상 로그인 감지용)

## DB 구조 (마이그레이션: database/migrations/2026_05_21_*)

총 **33개 마이그레이션** (시스템 3 + 도메인 30) — `migrate:fresh --seed`로 항상 재생성 가능.

### 그룹별 테이블
- **시스템**: sessions, password_reset_tokens, personal_access_tokens, cache(2), jobs(3)
- **코드/지역**: code_groups, codes, regions (시도+시군구)
- **회원/관계/인증**: users, user_relations(총판↔영업자/영업자↔학원), phone_verifications, kakao_oauth_links
- **거래처**: vendors(학원 마스터), vendor_users(학원의 담당자 N:M)
- **도서/재고**: publishers, books, book_targets(학년/난이도/학교 N:M), book_files, book_stocks(총판별)
- **할인율**: agent_vendor_discounts(영업자×학원), agent_vendor_book_discounts(교재별 오버라이드)
- **주문**: orders, order_items, order_status_logs, order_shipments
- **B2C**: academy_classes, parents, students, class_books, parent_share_links
- **알림/감사**: notification_templates, notifications, audit_logs, bulk_import_jobs, site_settings

### DB 초기화/시드
```bash
php artisan migrate:fresh --seed   # 전체 재생성 + 시드
```

### 시더 (database/seeders/)

**운영 배포용** — `php artisan db:seed --class=ProductionSeeder`
1. CodeTableSeeder — 21개 그룹, 115개 코드
2. RegionSeeder — 시도 17 + 시군구 229
3. PublisherSeeder — 출판사 18개
4. NotificationTemplateSeeder — 알림 템플릿 11개
5. SiteSettingSeeder — 사이트 설정 30개 (app 그룹 7개 포함, **idempotent — value 보존**)
6. AdminAccountSeeder — admin 계정 1개 (아이디 `admin01` / 임시 비번 `admin1234` — 첫 로그인 시 변경 강제)

**로컬/데모용** — `php artisan db:seed` (DatabaseSeeder)
- 위 운영 시더 5개 + 다음:
- DemoAccountSeeder — 총판/영업자/학원 데모 8명 + 거래처 3 + 관계 6 + 할인율 3
- DemoBookSeeder — 도서 15권 (source='demo', 가짜 ISBN — 알라딘에서 자동 채우려면 실제 ISBN 입력 필요)
- DemoOrderSeeder — 데모 주문 8건 (다양한 상태)

## 주요 구조

### 라우트
- `routes/web.php` — 관리자 + 공개 페이지
- `routes/api.php` — 모바일 API v1 (`/api/v1/*`, Sanctum 토큰)

### 컨트롤러 (app/Http/Controllers/)

**관리자 (Admin/)**
- `AuthController` — 로그인/로그아웃 (이메일+비번)
- `DashboardController` — 통계 카드 + 최근 가입
- `UserController` — 회원 CRUD + 승인 + 비번초기화 + 자기차단/슈퍼관리자 보호
- `VendorController` — 거래처 CRUD + 담당자/영업자 매핑/할인율
- `BookController` — 도서 CRUD + 알라딘 ISBN 자동조회/키워드 검색
- `BookImportController` — 엑셀 일괄 등록 (템플릿 다운로드/미리보기/임포트)
- `StockController` — 총판별 재고 조정 (인라인 + 일괄 저장)
- `OrderController` — 주문 목록/상세, 상태 전이, 송장 입력 + 알림 자동 발송
- `ClassController` — B2C 학급/학생/학부모 + 교재 매핑 + 공유링크
- `NotificationController` — 알림 템플릿 편집 + 발송 이력
- `SettingsController` — 사이트 설정 (회사/연동/SEO/정책)
- `CodeGroupController`, `CodeController` — 코드테이블
- `RegionController` — 시·군·구 ajax

**API (Api/)**
- `AuthController` — 휴대폰 인증/회원가입/로그인(토큰)/로그아웃/me
- `BookController` — 도서 검색 + ISBN 단권 조회 (바코드 스캔)
- `OrderController` — 주문 목록/상세/생성, 영업자 확정, 총판 접수

**공개**
- `ParentShareController` — `/p/{token}` 학부모 공유 페이지 (인증 불요)

### 서비스 (app/Services/)
- `AladinService` — 알라딘 TTB API 래퍼 (`lookupByIsbn`, `search`)
- `AligoService` — 알리고 알림톡 + SMS 래퍼 (`sendAlimtalk`, `sendSms`)
- `NotificationService` — 알림 도메인 서비스
  - `send($event, $context, $recipients)` — 모든 활성 템플릿 발송
  - `sendPhoneVerification($phone, $code)` — 휴대폰 인증번호 발송
  - 변수 치환: `#{key}` 패턴, 숫자 자동 천단위 포맷
  - 모든 발송 결과 `notifications` 테이블에 기록 (sent/failed/skipped)
- `BookImportService` — 엑셀 파싱 + 검증 + 임포트 (`parse`, `import`, `generateTemplate`)

### 헬퍼 (app/helpers.php — composer autoload)
- `setting($key, $default)` — 사이트 설정 값 조회 (배열 캐싱)
- `setting_image($key, $default)` — 이미지 경로 처리

### 모델 (app/Models/)
- `User` (SoftDeletes + HasApiTokens) — `isAdmin()`, `isSuperAdmin()`, `isAgent()` 등 헬퍼
- `Vendor`, `Book`, `Publisher`, `Order`, `OrderItem`, `SiteSetting`

### 미들웨어 (app/Http/Middleware/)
- `EnsureAdmin` — 관리자 전용 (role_code=admin + status=active)

### 뷰 (resources/views/)
- 공개: `welcome.blade.php`, `parent/{share,share_expired}.blade.php`
- 관리자 (`admin/`)
  - 레이아웃: `layouts/admin.blade.php`, `partials/{sidebar,topbar}.blade.php`
  - 인증: `auth/login.blade.php`
  - 대시보드: `dashboard/index.blade.php`
  - 사용자: `users/{index,create,show,pending}.blade.php`
  - 거래처: `vendors/{index,create,show}.blade.php`
  - 도서: `books/{index,create,show,import,import_preview}.blade.php`
  - 재고: `stocks/index.blade.php`
  - 주문: `orders/{index,show}.blade.php`
  - 학급: `classes/{index,create,show}.blade.php`
  - 알림: `notifications/{templates,logs}.blade.php`
  - 코드: `codes/{groups,codes}.blade.php`
  - 설정: `settings/edit.blade.php`

### 자산
- `public/css/admin.css` — 사이드바·로그인·통계카드 등
- `public/js/admin.js` — 사이드바 모바일 토글
- 업로드 이미지: `storage/app/public/` → `public/storage` 심볼릭 링크 완료

## 디자인 규칙 (반드시 준수)

- 배경 흰색, 모던. **이모지 사용 금지**. 아이콘은 Bootstrap Icons.
- 색상은 무채색 + 단일 액센트(딥 네이비 `#1f3a5f`)만. 알록달록 X.
- 모바일에서 깨지지 않게 반응형 필수.
- 관리자 좌측 메뉴는 **독립 스크롤** (`position:fixed` 사이드바 + `.admin-nav` overflow).
- 이미지는 액박(깨진 이미지) 금지 — 항상 실제 파일이 존재해야 함.
- 사용자 정보 편집/거래처 편집/도서 편집은 **모두 한 페이지에 통합** (좌: 폼, 우: 관계/이력).

## 외부 연동 (관리자 > 사이트 설정 > 외부 연동에서 키 입력)

- **알리고 알림톡 + SMS** ✅ 구현 완료 (`AligoService`)
  - 설정 키: `aligo_api_key`, `aligo_user_id`, `aligo_sender_key`, `aligo_sender`, `aligo_admin_phone`
  - 미입력 시 `notifications.status='skipped'`로 기록 (호출 코드는 영향 없음)
  - 알림톡 템플릿은 알리고에 사전 등록·승인 필요 (8개 이벤트)
  - 알림톡 실패 시 SMS 자동 폴백 (`failover_1='Y'`)
- **알라딘 TTB** ✅ 구현 완료 (`AladinService`)
  - 설정 키: `aladin_ttb_key` (현재 발급된 키: `ttbbarijo0654001`)
  - **허용 도메인: booksys.co.kr 한정** — 운영 배포 도메인은 이걸로 가야 함
  - `lookupByIsbn`, `search` 메서드. 응답을 BookFlow 스키마로 정규화
  - 미입력 시 친절한 에러 반환. 앱: https://blog.aladin.co.kr/openapi
  - 로컬(127.0.0.1)에서 호출은 일단 동작하지만, 운영은 반드시 booksys.co.kr
- **카카오 OAuth**: `kakao_client_id`, `kakao_client_secret` — 추후 구현
- **FCM**: `fcm_server_key`, `fcm_project_id` — 추후 (현재 push 채널은 `skipped`)
- **이메일**: 현재 `MAIL_MAILER=log` (실제 발송 안 함, 로그만)
- `.env`에도 `ALIGO_*`, `ALADIN_TTB_KEY`, `KAKAO_*`, `FCM_*` 폴백 키가 있음 (관리자 설정값 우선)

## 자동 알림 시점 (8개) — 알리고 템플릿 신청 필요

| event_code | 시점 | 받는 사람 | 채널 |
|---|---|---|---|
| `user.phone_verify` | 회원가입 휴대폰 인증 | 본인 | SMS |
| `user.approval_result` | 가입 승인/거절 | 본인 | 알림톡 |
| `order.requested` | 학원 주문 접수 | 영업자 | 푸시 + 알림톡 |
| `order.confirmed` | 영업자 확정 | 학원, 총판 | 푸시 + 알림톡 |
| `order.accepted` | 총판 접수 | 영업자, 학원 | 알림톡 |
| `order.shipped` | 출고/송장입력 | 학원 | 알림톡 |
| `order.canceled` | 주문 취소 | 관련자 전원 | 알림톡 |
| `b2c.share_link` | 학부모 공유링크 | 학부모 | 알림톡/SMS |

## 안전장치

- **자기 차단 방지**: 본인 계정의 일시정지/거래종료/비번초기화 **차단** (UI + 컨트롤러 양쪽)
- **슈퍼관리자 보호**: 슈퍼관리자가 아닌 사람이 슈퍼관리자 권한 부여/박탈/비활성화 **차단**
- **거래처/도서 삭제**: 주문 이력 있으면 **삭제 차단**, 상태 변경(거래종료/절판)으로 유도
- **영업자 매핑 해제**: 주문 이력 있으면 **비활성화만**, 없으면 삭제

## SEO

- 메타태그/OG/Twitter는 추후 `home.blade.php` 작성 시 사이트 설정에서 생성
- `/sitemap.xml`, `/robots.txt` — 추후 구현

## 캐시/문제 해결

- 코드 수정 후: `php artisan view:clear`, `config:clear`, `cache:clear`
- 사이트 설정 반영 안 되면: `SiteSetting::flush()` 또는 `cache:clear`
- MySQL CLI에서 한글 깨져 보이는 건 Windows 콘솔 표시 문제 (저장 데이터는 utf8mb4 정상)
- `auth` 미들웨어가 `login` 라우트를 찾는 문제: `bootstrap/app.php`에서 `redirectGuestsTo(fn () => route('admin.login'))` 처리됨

## 현재 진행 상황 (2026-05-22)

### ✅ 완료 (1단계 MVP 거의 다)

**기반**
- 마이그레이션 33개 + 시드 7개 (DemoOrderSeeder 포함)
- 컨트롤러 14개 (Admin 12 + Api 3)
- 서비스 4개 (Aladin / Aligo / Notification / BookImport)
- Sanctum 설치 + API 토큰 인증

**회원·관계**
- 관리자 로그인 + 미들웨어 (자기차단/슈퍼관리자 보호)
- 대시보드 (통계 카드 + 최근 가입)
- 사용자 CRUD (목록/등록/상세통합 + 비번초기화 + 관계/주문)
- 시·도 → 시·군·구 cascading select (ajax)

**거래처·도서·재고**
- 거래처(학원) CRUD + 담당자/영업자 할인율 매핑
- 도서 CRUD + 학년/난이도 N:M + 총판별 재고
- 알라딘 TTB 연동 (ISBN 자동조회 + 키워드 검색)
- **도서 엑셀 대량 업로드** (템플릿 다운로드/미리보기/임포트)
- **재고 관리** (인라인 편집 + 일괄 저장, low stock 강조)

**주문**
- 주문 CRUD + 상태 전이 (`requested → confirmed → accepted → shipped → in_transit → completed` + 취소/반품)
- 송장 입력 자동 출고처리
- 상태 이력 자동 기록 + 알림 자동 트리거
- 데모 주문 8건 시드

**알림**
- AligoService — 알림톡 + SMS (실패 시 자동 SMS 폴백)
- NotificationService — 템플릿 렌더링 + 디스패치 + 로깅
- 자동 발송 훅: 회원 승인/거절, 주문 상태 변경, 출고, B2C 공유링크
- 관리자 화면: 템플릿 편집(`/admin/notifications/templates`), 발송 이력(`/admin/notifications/logs`)
- 키 미설정 시 `skipped` 상태로 안전하게 기록

**B2C**
- 학급 CRUD (학원별)
- 학생/학부모 등록 (통합 폼)
- 학급별 교재 매핑
- 학부모 공유링크 발급 + 알림톡/SMS 자동 발송
- 공개 페이지 `/p/{token}` (모바일 최적화, 접속 카운트)

**모바일 API (Sanctum)**
- `POST /api/v1/auth/phone/send|verify` — 휴대폰 인증
- `POST /api/v1/auth/register|login|logout` — 회원가입/로그인
- `GET /api/v1/me`
- `GET /api/v1/books?q=...` — 도서 검색
- `GET /api/v1/books/isbn/{isbn}` — ISBN 단권 조회 (바코드 스캔용)
- `GET /api/v1/orders` — 내 주문 (역할별 자동 필터)
- `POST /api/v1/orders` — 주문 생성 (할인율 자동 계산 + 총판 자동 라우팅 + 알림)
- `POST /api/v1/orders/{order}/confirm|accept` — 영업자 확정/총판 접수

**공개 사이트 (5/22 추가)**
- 공개 랜딩 페이지 (welcome) — Hero / 5단계 사용자 / 핵심 기능 6개 / 주문 흐름 / 회사 정보
- 공개 회원가입/로그인 (`/login`, `/register`, `/register/done`)
- 마이페이지 (`/mypage`) — 역할별 위젯 분기 (총판/영업자/학원 + 본인 정보 + 최근 주문)
- 마이페이지 정보/비번 수정 (`/mypage/profile`)
- 앱 다운로드 섹션 (Android APK + iOS App Store, 관리자에서 URL 입력)
- SEO: OG + Twitter Card + JSON-LD + sitemap.xml + robots.txt
- 커스텀 404/500 페이지 (한글, BookFlow 디자인)
- 시간대 Asia/Seoul (PHP + DB 일치)
- 세션 30일 유지 (`SESSION_LIFETIME=43200`, `SESSION_EXPIRE_ON_CLOSE=false`)

**관리자 추가 (5/22)**
- 감사 로그 화면 (`/admin/audit-logs`) — diff 보기, 필터, 자동 기록
- 지역 관리 (`/admin/regions`) — 시도/시군구 CRUD
- 알림 템플릿 + 발송 이력 (`/admin/notifications/*`)
- 대시보드 위젯 보강 (4×4 카드: 통계 + 역할 + 활동)

**기타**
- 코드테이블 CRUD (그룹 + 하위 코드)
- 사이트 설정 (회사/앱/연동/SEO/정책 탭별 편집, idempotent 시더)
- 시도/시군구 cascading select (ajax)

### 🟢 알라딘 TTB Key 발급 완료
- 키: `ttbbarijo0654001` (`site_settings.aladin_ttb_key`에 저장됨)
- **허용 도메인: booksys.co.kr** — 운영 도메인은 반드시 이걸로 (서브도메인 OK)
- ItemLookUp + ItemSearch 동작 검증 완료 (2026-05-21)
- 출판사 자동 매칭/생성 동작 확인 (`firstOrCreate`)
- 시드 도서의 ISBN(`9788901234001~015`)은 가짜이므로 실제 운영용 도서는 진짜 ISBN으로 재등록 필요

### 🟡 키 발급 대기 중
- **알리고 알림톡 키** — 키 입력 시 8개 이벤트 알림톡 실제 발송 시작
  - 알림톡 템플릿은 알리고에 사전 등록·승인 필요
  - 키 없어도 `skipped` 상태로 로그 정상 기록됨

### 🔜 남은 작업
1. **AWS Lightsail 서버 배포** — 진행 중 (인스턴스 생성됨, IP 할당 + 도메인 매핑 + Docker 배포 단계)
2. **알리고 알림톡 키 입력** — 받으면 site_settings에 입력
3. **Flutter 앱** — 별도 프로젝트로 시작 (영업자/학원용)
4. **(선택) 결제 PG 연동** — B2C 학부모 결제 (1단계 제외)
5. **(선택) 정산/세금계산서** — 결제 도입 후

### 🏗 인프라 결정 (2026-05-22)
- **호스팅**: AWS Lightsail 서울 리전 (ap-northeast-2)
  - 사양: Standard 2vCPU / 4GB RAM / 80GB SSD / 트래픽 5TB
  - 월 비용: $20 (약 28,000원)
  - OS: Ubuntu 22.04 LTS
  - 인스턴스명: `booksys`
- **도메인**: `booksys.co.kr` (가비아 등록, 가비아 네임서버)
  - 가비아 DNS A 레코드 → Lightsail 정적 IP로 매핑 예정
- **DB**: Lightsail 인스턴스 내 MariaDB (Docker)
  - 트래픽 늘면 외부 관리형 DB로 분리 검토
- **배포**: Docker (우리가 만든 Dockerfile + docker-compose.yml)
- **K8s manifests**: 보관 (사용자 폭증 시 K8s 클러스터 이전 시 사용)
- **CI/CD**: 1단계 수동 배포 (git pull + docker compose up -d --build), 추후 GitHub Actions

## 배포

**🔑 운영 도메인: `booksys.co.kr`** (알라딘 TTB Key가 이 도메인 한정)

### Option A — Kubernetes (권장, 클러스터 확보 시)

준비된 파일:
- `Dockerfile` (production용 단일 이미지: PHP 8.3-fpm + nginx + supervisord)
- `docker-compose.yml` (로컬 dev)
- `docker/` (php.ini, opcache.ini, www.conf, nginx.conf, default.conf, supervisord.conf, entrypoint.sh)
- `.dockerignore`
- `k8s/` (Namespace, ConfigMap, Secret 예시, Deployment×2 app+redis, Service, Ingress, Job 마이그레이션, HPA, README)

자세한 가이드: **`k8s/README.md`**

### Option B — 전통적 SSH/FTP 배포

1. 소스 업로드 (vendor 제외, 서버에서 `composer install --no-dev -o`)
2. `.env` 생성: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://booksys.co.kr`, DB 정보
3. `php artisan key:generate`
4. `php artisan migrate --force`
5. `php artisan db:seed --class=ProductionSeeder --force`
6. `php artisan storage:link`
7. `storage/`, `bootstrap/cache/` 쓰기 권한
8. `php artisan config:cache route:cache view:cache`
9. 웹 루트(docroot)를 `public/` 로 지정

### Docker 로컬 테스트

```bash
cp .env .env.docker  # DB_HOST=db, REDIS_HOST=redis 로 변경 (또는 docker-compose의 environment 사용)
docker compose up -d --build
docker compose exec app php artisan migrate:fresh --seed
open http://localhost:8778
```

## 관련 프로젝트

- 같은 회사(e-Learn)의 또 다른 사이트: `C:\laragon\www\business_site` (이런 e-learn — 개발의뢰 회사 홈페이지)
- 디자인 톤·코딩 스타일은 business_site와 통일

## 통계 (2026-05-22 기준)

- 라우트 총 108+개 (관리자 + API + 공개 + 마이페이지 + sitemap)
- 마이그레이션 33개
- 시드 9개 (CodeTable / Region / Publisher / NotificationTemplate / SiteSetting / AdminAccount / DemoAccount / DemoBook / DemoOrder)
- 컨트롤러 16개 (Admin 14 + Api 3 + Public 3: PublicAuth/MyPage/ParentShare/Sitemap)
- 서비스 4개 (Aladin / Aligo / Notification / BookImport)
- 관리자 메뉴 11개 (대시보드, 회원·승인대기열, 거래처, 도서·엑셀, 재고, 주문, 학급, 알림템플릿·이력, 감사로그, 지역, 코드테이블, 사이트설정)
- 공개 페이지: 랜딩(welcome) / 로그인 / 회원가입 / 마이페이지 / 학부모공유링크 / sitemap.xml
- Docker + K8s manifests 보관 중 (사용자 폭증 시 K8s 이전 가능)
- **2차 정밀 점검 통과** — PHP/Blade 0 오류, 모든 페이지 200, 권한 격리, CSRF, XSS 0
- 키 미설정 상태에서도 모든 화면/API 정상 동작 (알림은 `skipped` 기록)

## 키 발급 후 빠른 테스트 절차

### 알라딘 TTB Key
```
1. /admin/settings/integration → 알라딘 TTB API Key 입력 → 저장
2. /admin/books/create
3. ISBN13 칸에 실제 ISBN 입력 (예: 9788970438368) → "조회" 클릭
4. 자동 채움 확인 → 등록
```

### 알리고 알림톡
```
1. 알리고에 회원가입 + 발신프로필 등록
2. 알림톡 템플릿 8개 사전 등록·승인 받기 (event_code 참고)
3. /admin/settings/integration → 알리고 키 5개 입력
4. /admin/notifications/templates → 각 템플릿에 알리고 tpl_code 입력
5. /admin/orders/{id} → 상태 변경 → /admin/notifications/logs 에서 sent 확인
```
