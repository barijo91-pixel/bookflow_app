@extends('public.layouts.app')
@section('title', '주문 #'.$order->order_no)

@section('content')
@php
    $statusOptions = [
        'requested'  => ['접수', 'bg-warning text-dark'],
        'confirmed'  => ['확정', 'bg-info'],
        'accepted'   => ['총판 접수', 'bg-primary'],
        'shipped'    => ['출고', 'bg-success'],
        'in_transit' => ['배송중', 'bg-success'],
        'completed'  => ['완료', 'bg-dark'],
        'canceled'   => ['취소', 'bg-secondary'],
        'returned'   => ['반품', 'bg-secondary'],
    ];
    $opt = $statusOptions[$order->status_code] ?? [$order->status_code, 'bg-light text-dark'];
@endphp

<div class="mb-3 d-flex justify-content-between align-items-start">
    <div>
        <a href="{{ route('my.orders.index') }}" class="text-muted small text-decoration-none">
            <i class="bi bi-arrow-left"></i> 주문 목록으로
        </a>
        <h1 class="h4 navy mt-1 mb-1">
            <i class="bi bi-receipt"></i> 주문 <code>{{ $order->order_no }}</code>
        </h1>
        <p class="mb-0">
            <span class="badge {{ $opt[1] }}">{{ $opt[0] }}</span>
            <span class="text-muted small ms-2">{{ \Carbon\Carbon::parse($order->created_at)->format('Y-m-d H:i') }}</span>
        </p>
    </div>
</div>

<div class="row g-3">
    {{-- 좌측: 정보 + 액션 --}}
    <div class="col-lg-5">
        <div class="card section-card mb-3">
            <div class="card-header"><strong><i class="bi bi-info-circle"></i> 주문 정보</strong></div>
            <div class="card-body">
                <dl class="row small mb-0">
                    <dt class="col-4 text-muted">학원</dt>
                    <dd class="col-8 fw-bold">{{ $vendor->name ?? '-' }}</dd>
                    @if(!empty($class))
                        <dt class="col-4 text-muted">학급</dt>
                        <dd class="col-8"><span class="badge bg-light text-dark"><i class="bi bi-mortarboard"></i> {{ $class->name }}</span></dd>
                    @endif
                    @if(!empty($orderStudents) && $orderStudents->count())
                        <dt class="col-4 text-muted">대상 학생 ({{ $orderStudents->count() }})</dt>
                        <dd class="col-8">
                            @foreach($orderStudents as $os)
                                <span class="badge bg-light text-dark mb-1">{{ $os->student_name }}@if($os->parent_name) · {{ $os->parent_name }}@endif</span>
                            @endforeach
                        </dd>
                    @endif
                    <dt class="col-4 text-muted">영업자</dt>
                    <dd class="col-8">{{ $agent->name ?? '-' }}</dd>
                    <dt class="col-4 text-muted">총판</dt>
                    <dd class="col-8">{{ $dist->name ?? '(미배정)' }}</dd>
                    <dt class="col-4 text-muted">배송 방식</dt>
                    <dd class="col-8 mb-0">
                        @if(($order->delivery_type ?? 'parcel') === 'direct')
                            <span class="badge bg-warning text-dark">직접 배송</span>
                        @else
                            <span class="badge bg-light text-dark">택배</span>
                        @endif
                    </dd>
                </dl>
            </div>
            {{-- 금액 — 시각적으로 분리된 강조 영역 --}}
            <div class="card-footer">
                <div class="d-flex justify-content-between small text-muted mb-1">
                    <span>소계</span>
                    <span>{{ number_format($order->subtotal_amount) }}원</span>
                </div>
                <div class="d-flex justify-content-between small text-muted mb-2">
                    <span>배송비</span>
                    <span>{{ number_format($order->shipping_fee) }}원</span>
                </div>
                <div class="d-flex justify-content-between align-items-baseline pt-2 border-top">
                    <span class="fw-bold navy">총액</span>
                    <span class="h4 navy mb-0">{{ number_format($order->total_amount) }}원</span>
                </div>
            </div>
        </div>

        {{-- 액션 카드 (결제 액션은 우측 상단으로 분리) --}}
        @if($canConfirm || $canAccept || $canShip || $canCancel || $canEdit)
            <div class="card section-card mb-3">
                <div class="card-header"><strong><i class="bi bi-lightning"></i> 처리</strong></div>
                <div class="card-body">
                    @if($canEdit)
                        <a href="{{ route('my.orders.edit', $order->id) }}" class="btn btn-outline-primary w-100 mb-2">
                            <i class="bi bi-pencil-square"></i> 주문 수정 (수량/도서 삭제)
                        </a>
                    @endif
                    @if($canConfirm)
                        <form method="POST" action="{{ route('my.orders.transition', $order->id) }}" class="mb-2"
                              onsubmit="return confirm('주문을 확정하시겠습니까? 확정 후 총판에게 전달됩니다.')">
                            @csrf
                            <input type="hidden" name="to_status" value="confirmed">
                            <div class="form-check form-switch mb-2">
                                <input type="checkbox" name="delivery_type" value="direct" class="form-check-input" id="deliveryDirect">
                                <label for="deliveryDirect" class="form-check-label small">
                                    <strong>직접 배송 요청</strong> <span class="text-muted">(대형 학원·택배 X)</span>
                                </label>
                            </div>
                            <button class="btn btn-primary w-100">
                                <i class="bi bi-check-lg"></i> 주문 확정
                            </button>
                        </form>
                    @endif

                    @if($canAccept)
                        <form method="POST" action="{{ route('my.orders.transition', $order->id) }}" class="mb-2"
                              onsubmit="return confirm('주문을 접수하시겠습니까? 접수 후 출고 준비 단계로 진행됩니다.')">
                            @csrf
                            <input type="hidden" name="to_status" value="accepted">
                            <button class="btn btn-primary w-100">
                                <i class="bi bi-check-lg"></i> 총판 접수
                            </button>
                        </form>
                    @endif

                    @if($canShip)
                        @php $isDirect = ($order->delivery_type ?? 'parcel') === 'direct'; @endphp
                        <form method="POST" action="{{ route('my.orders.ship', $order->id) }}" class="mb-2">
                            @csrf
                            @if($isDirect)
                                {{-- 직접배송: 화물·용달 기사 정보 입력 (계획서 6-2장) --}}
                                <div class="alert alert-warning py-2 small mb-2">
                                    <i class="bi bi-truck"></i> <strong>직접배송 요청</strong> — 화물·용달 배차 후 기사 정보를 입력해주세요.
                                    @if(! empty($order->delivery_memo))
                                        <div class="small text-muted mt-1">📝 영업자 메모: {{ $order->delivery_memo }}</div>
                                    @endif
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small text-muted mb-1">기사 이름 *</label>
                                    <input type="text" name="driver_name" class="form-control form-control-sm" maxlength="50" required>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small text-muted mb-1">기사 연락처 *</label>
                                    <input type="tel" name="driver_phone" class="form-control form-control-sm" maxlength="20" placeholder="010-0000-0000" required>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-7">
                                        <label class="form-label small text-muted mb-1">차량번호 (선택)</label>
                                        <input type="text" name="vehicle_no" class="form-control form-control-sm" maxlength="20" placeholder="12가 3456">
                                    </div>
                                    <div class="col-5">
                                        <label class="form-label small text-muted mb-1">배송비 (원)</label>
                                        <input type="number" name="delivery_fee" class="form-control form-control-sm text-end" min="0" step="1000" value="0">
                                    </div>
                                </div>
                                <button class="btn btn-success w-100">
                                    <i class="bi bi-send"></i> 배차 정보 저장 + 출고 처리
                                </button>
                            @else
                                {{-- 택배 --}}
                                <div class="mb-2">
                                    <label class="form-label small text-muted mb-1">택배사</label>
                                    <select name="courier_code" class="form-select form-select-sm" required>
                                        <option value="">선택</option>
                                        @foreach($courierOptions as $c)
                                            <option value="{{ $c->code }}">{{ $c->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small text-muted mb-1">송장번호</label>
                                    <input type="text" name="tracking_no" class="form-control form-control-sm" required>
                                </div>
                                <button class="btn btn-success w-100">
                                    <i class="bi bi-truck"></i> 출고 처리
                                </button>
                            @endif
                        </form>
                    @endif

                    {{-- 영업자 전용: 배송 안내 + 직접배송(옵션) — 확정/접수 단계, 일반배송일 때 --}}
                    @if($user->role_code === 'agent' && $order->agent_user_id == $user->id
                        && in_array($order->status_code, ['confirmed', 'accepted'], true)
                        && ($order->delivery_type ?? 'parcel') !== 'direct')
                        <div class="mt-3 pt-3 border-top">
                            {{-- 일반배송(택배)이 기본임을 안내 --}}
                            <div class="alert alert-success small mb-2">
                                <i class="bi bi-check-circle-fill"></i>
                                @if($order->status_code === 'confirmed')
                                    주문이 <strong>확정</strong>되어 총판({{ $dist->name ?? '총판' }})에 전달되었습니다.
                                    기본 <strong>택배</strong>로 출고되며 영업자가 더 할 일은 없습니다.
                                @else
                                    총판이 <strong>접수</strong>했습니다. 기본 <strong>택배</strong>로 출고됩니다.
                                @endif
                            </div>

                            {{-- 직접배송은 옵션 (접이식) --}}
                            <div class="small text-muted mb-2">
                                <i class="bi bi-info-circle"></i>
                                대형 학원·고중량 등 <strong>화물·용달 직접배송</strong>이 필요할 때만 아래에서 신청하세요.
                            </div>
                            <form method="POST" action="{{ route('my.orders.direct_delivery', $order->id) }}"
                                  onsubmit="return confirm('이 주문을 직접배송(화물·용달)으로 변경 신청할까요?')">
                                @csrf
                                <div class="mb-2">
                                    <label class="form-label small fw-bold navy mb-1">
                                        <i class="bi bi-truck"></i> 직접배송 메모
                                    </label>
                                    <textarea name="delivery_memo" class="form-control form-control-sm" rows="2"
                                              maxlength="500" placeholder="예: 당일 배송 필요 / 근거리 직납 / 고중량"></textarea>
                                </div>
                                <button class="btn btn-warning text-dark fw-bold w-100">
                                    <i class="bi bi-truck"></i> 직접배송 신청
                                    <span class="fw-normal small">(일반배송은 해당 없음)</span>
                                </button>
                                <div class="small text-muted mt-1">배송비는 총판이 별도 청구합니다.</div>
                            </form>
                        </div>
                    @endif

                    @if($canCancel)
                        {{-- 위험 액션 — 다른 버튼과 시각적 분리 --}}
                        <div class="mt-3 pt-3 border-top">
                            <form method="POST" action="{{ route('my.orders.transition', $order->id) }}"
                                  onsubmit="return confirm('주문을 취소하시겠습니까? 되돌릴 수 없습니다.')">
                                @csrf
                                <input type="hidden" name="to_status" value="canceled">
                                <div class="mb-2">
                                    <label class="form-label small fw-bold navy mb-1">취소 사유 (선택)</label>
                                    <textarea name="reason" class="form-control" rows="2" maxlength="500" placeholder="예: 고객 요청, 재고 부족, 학원 측 변경 요청 등"></textarea>
                                </div>
                                <button class="btn btn-danger w-100">
                                    <i class="bi bi-x-circle"></i> 주문 취소
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- 출고 정보 --}}
        @if($shipment)
            @php $isDirect = ($order->delivery_type ?? 'parcel') === 'direct'; @endphp
            <div class="card section-card mb-3">
                <div class="card-header"><strong><i class="bi bi-truck"></i> 출고/배송</strong></div>
                <div class="card-body small">
                    <dl class="row mb-0">
                        <dt class="col-4 text-muted">배송 방식</dt>
                        <dd class="col-8">
                            @if($isDirect)
                                <span class="badge bg-warning text-dark">직접배송 (화물·용달)</span>
                            @else
                                <span class="badge bg-light text-dark">택배</span>
                            @endif
                        </dd>
                        @if($isDirect)
                            {{-- 직접배송: 기사 정보 (계획서 6-2장) --}}
                            @if($shipment->driver_name)
                                <dt class="col-4 text-muted">기사 이름</dt>
                                <dd class="col-8"><strong>{{ $shipment->driver_name }}</strong></dd>
                                <dt class="col-4 text-muted">연락처</dt>
                                <dd class="col-8">
                                    <a href="tel:{{ $shipment->driver_phone }}" class="text-decoration-none">
                                        <i class="bi bi-telephone"></i> {{ format_phone($shipment->driver_phone) }}
                                    </a>
                                </dd>
                                @if($shipment->vehicle_no)
                                    <dt class="col-4 text-muted">차량번호</dt>
                                    <dd class="col-8"><code>{{ $shipment->vehicle_no }}</code></dd>
                                @endif
                                @if($shipment->delivery_fee > 0)
                                    <dt class="col-4 text-muted">배송비</dt>
                                    <dd class="col-8 fw-bold">{{ number_format($shipment->delivery_fee) }}원 <span class="small text-muted">(총판 → 사입자 청구)</span></dd>
                                @endif
                            @else
                                <dt class="col-4 text-muted">상태</dt>
                                <dd class="col-8">
                                    @if($shipment->direct_requested_at)
                                        <span class="badge bg-info">배차 대기 중</span>
                                        <div class="small text-muted mt-1">신청: {{ \Carbon\Carbon::parse($shipment->direct_requested_at)->format('m-d H:i') }}</div>
                                    @else
                                        -
                                    @endif
                                </dd>
                            @endif
                        @else
                            {{-- 택배 --}}
                            <dt class="col-4 text-muted">택배사</dt>
                            <dd class="col-8">{{ $shipment->courier_code ?? '-' }}</dd>
                            <dt class="col-4 text-muted">송장번호</dt>
                            <dd class="col-8"><code>{{ $shipment->tracking_no ?? '-' }}</code></dd>
                        @endif
                        @if($shipment->shipped_at)
                            <dt class="col-4 text-muted">출고일</dt>
                            <dd class="col-8">{{ \Carbon\Carbon::parse($shipment->shipped_at)->format('Y-m-d H:i') }}</dd>
                        @endif
                    </dl>
                </div>
            </div>
        @endif
    </div>

    {{-- 우측: 결제 액션 + 주문 목록 + 상태 로그 --}}
    <div class="col-lg-7">
        {{-- 학원 결제 액션 (우측 상단 강조) --}}
        @if($user->role_code === 'academy' && in_array($order->status_code, ['requested','confirmed','accepted','shipped']))
            <div class="card section-card mb-3 border-warning">
                <div class="card-body">
                    @if(($vendor->trade_type ?? 'retail') === 'wholesale')
                        {{-- 도매: 학원이 직접 결제 (학부모 거치지 않음) --}}
                        <form method="POST" action="{{ route('my.orders.pay_direct', $order->id) }}"
                              onsubmit="return confirm('교재비 {{ number_format($order->total_amount) }}원을 결제할까요?')">
                            @csrf
                            <button class="btn btn-warning w-100 text-dark fw-bold">
                                <i class="bi bi-credit-card"></i> 교재비 결제 ({{ number_format($order->total_amount) }}원)
                            </button>
                        </form>
                    @else
                        {{-- 소매: 학부모에게 결제 요청 --}}
                        <a href="{{ route('my.orders.payment.create', $order->id) }}" class="btn btn-warning w-100">
                            <i class="bi bi-chat-dots-fill"></i> 학부모에게 결제 요청
                        </a>
                    @endif
                </div>
            </div>
        @endif

        <div class="card section-card mb-3">
            <div class="card-header"><strong><i class="bi bi-book"></i> 주문 목록 ({{ $items->count() }}건)</strong></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>도서</th>
                            <th class="text-end">단가</th>
                            <th class="text-end">수량</th>
                            <th class="text-end">소계</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $it)
                            <tr>
                                <td class="small">
                                    <strong>{{ $it->book_title ?? $it->title_snapshot ?? '-' }}</strong>
                                    @if($it->book_isbn)<div class="text-muted"><code>{{ $it->book_isbn }}</code></div>@endif
                                </td>
                                <td class="text-end small">{{ number_format($it->unit_price) }}원</td>
                                <td class="text-end small">{{ $it->qty }}</td>
                                <td class="text-end small fw-bold">{{ number_format($it->line_total) }}원</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if($payers->isNotEmpty())
        <div class="card section-card mb-3">
            <div class="card-header"><strong><i class="bi bi-people"></i> 구매 학부모 ({{ $payers->count() }})</strong></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>학생</th><th>학부모</th><th>연락처</th><th class="text-end">금액</th><th>상태</th></tr>
                    </thead>
                    <tbody>
                        @foreach($payers as $p)
                            <tr>
                                <td class="small">{{ $p->student_name ?? '-' }}</td>
                                <td class="small">{{ $p->parent_name ?? '-' }}</td>
                                <td class="small text-muted">{{ $p->parent_phone ? format_phone($p->parent_phone) : '-' }}</td>
                                <td class="text-end small">{{ number_format($p->amount) }}원</td>
                                <td>
                                    @if($p->status === 'paid' || $p->paid_at)
                                        <span class="badge bg-success">결제완료</span>
                                    @else
                                        <span class="badge bg-warning text-dark">대기</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        <div class="card section-card">
            <div class="card-header"><strong><i class="bi bi-clock-history"></i> 주문현황</strong></div>
            <div class="card-body">
                @if($statusLogs->isEmpty())
                    <div class="empty-state small">
                        <i class="bi bi-clock"></i>
                        아직 이력이 없습니다.
                    </div>
                @else
                    @php
                        $statusLabel = [
                            'requested'  => '접수',         'confirmed' => '확정',
                            'accepted'   => '총판 접수',    'shipped'   => '출고',
                            'in_transit' => '배송중',       'completed' => '완료',
                            'canceled'   => '취소',         'returned'  => '반품',
                        ];
                    @endphp
                    <ul class="timeline-list mb-0">
                        @foreach($statusLogs as $log)
                            @php
                                $from = $statusLabel[$log->from_status] ?? $log->from_status;
                                $to   = $statusLabel[$log->to_status]   ?? $log->to_status;
                            @endphp
                            <li class="timeline-item">
                                <div class="timeline-dot"></div>
                                <div class="timeline-content small">
                                    <strong class="navy">{{ $to }}</strong>
                                    <span class="text-muted ms-1">← {{ $from }}</span>
                                    <div class="text-muted small">
                                        <i class="bi bi-person"></i> {{ $log->changed_by_name ?? '시스템' }}
                                        · {{ \Carbon\Carbon::parse($log->created_at)->format('m-d H:i') }}
                                    </div>
                                    @if($log->reason)<div class="text-muted small fst-italic mt-1">"{{ $log->reason }}"</div>@endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
