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
                                <span class="badge bg-light text-dark me-1 mb-1">{{ $os->student_name }}@if($os->parent_name) <span class="text-muted fw-normal">(학부모 {{ $os->parent_name }})</span>@endif</span>
                            @endforeach
                        </dd>
                    @endif
                    <dt class="col-4 text-muted">영업자</dt>
                    <dd class="col-8">{{ $agent->name ?? '-' }}</dd>
                    {{-- 총판은 학원에게 감춘다 — 학원은 영업자와 거래하고 총판은 내부 유통 단계 --}}
                    @if($user->role_code !== 'academy')
                        <dt class="col-4 text-muted">총판</dt>
                        <dd class="col-8">{{ $dist->name ?? '(미배정)' }}</dd>
                    @endif
                    <dt class="col-4 text-muted">수령처</dt>
                    <dd class="col-8">
                        @if(($order->ship_to_type ?? 'parent') === 'vendor')
                            <span class="badge bg-primary"><i class="bi bi-building"></i> 학원 일괄</span>
                            <span class="text-muted small ms-1">학원에서 학생에게 전달</span>
                        @else
                            <span class="badge bg-info text-dark"><i class="bi bi-house"></i> 학부모 개별</span>
                        @endif
                    </dd>
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
                                    주문이 <strong>확정</strong>되어 총판@if($user->role_code !== 'academy')({{ $dist->name ?? '총판' }})@endif에 전달되었습니다.
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

        {{-- 반품 (영업자·총판) — 품목별 수량 접수, 총판 확정 시 PG 부분취소 --}}
        {{-- 학원은 읽기전용 (반품이 있을 때만) — 접수·확정은 영업자·총판 몫 --}}
        @if(!empty($canReturn) || (isset($orderReturns) && $orderReturns->count()))
            <div class="card section-card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong><i class="bi bi-arrow-return-left"></i> 반품</strong>
                    @if(!empty($canReturn) && collect($returnable)->sum('left') > 0)
                        <button type="button" class="btn btn-sm btn-outline-primary"
                                data-bs-toggle="modal" data-bs-target="#returnModal">
                            <i class="bi bi-plus-lg"></i> 반품 접수
                        </button>
                    @endif
                </div>
                <div class="card-body small">
                    @if(isset($orderReturns) && $orderReturns->count())
                        @foreach($orderReturns as $rt)
                            @php
                                $rtBadge = ['requested' => ['접수', 'bg-warning text-dark'], 'confirmed' => ['확정', 'bg-success'],
                                            'rejected' => ['반려', 'bg-secondary'], 'canceled' => ['취소', 'bg-secondary']][$rt->status] ?? [$rt->status, 'bg-light text-dark'];
                            @endphp
                            <div class="d-flex justify-content-between align-items-center border-bottom py-1">
                                <span>
                                    <span class="badge {{ $rtBadge[1] }}">{{ $rtBadge[0] }}</span>
                                    <code class="ms-1">{{ $rt->return_no }}</code>
                                    <span class="text-muted ms-1">{{ \Carbon\Carbon::parse($rt->requested_at)->format('m-d') }}</span>
                                </span>
                                <span class="fw-bold">{{ number_format($rt->total_qty) }}권 · {{ number_format($rt->total_amount) }}원
                                    @if($rt->status === 'confirmed' && $rt->refund_status === 'done')
                                        <span class="text-success fw-normal">환불됨</span>
                                    @elseif($rt->status === 'confirmed' && in_array($rt->refund_status, ['failed', 'partial']))
                                        <span class="text-danger fw-normal">환불 미완</span>
                                    @endif
                                </span>
                            </div>
                        @endforeach
                        @if($user->role_code !== 'academy')
                            <div class="text-end mt-2">
                                <a href="{{ route('my.returns.index') }}" class="link-name">반품 관리에서 처리 <i class="bi bi-arrow-right"></i></a>
                            </div>
                        @endif
                    @else
                        <span class="text-muted">접수된 반품이 없습니다. 품목별로 수량을 지정해 접수하면 총판 확정 시 환불됩니다.</span>
                    @endif
                </div>
            </div>

            @if(!empty($canReturn))
            <div class="modal fade" id="returnModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <form method="POST" action="{{ route('my.returns.store', $order->id) }}" class="modal-content">
                        @csrf
                        <div class="modal-header py-2">
                            <strong class="navy"><i class="bi bi-arrow-return-left"></i> 반품 접수</strong>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <table class="table table-sm small align-middle mb-3">
                                <thead class="table-light">
                                    <tr><th>교재</th><th class="text-end">주문</th><th class="text-end">반품 가능</th><th style="width:90px;">반품 수량</th></tr>
                                </thead>
                                <tbody>
                                    @foreach($items as $it)
                                        @php $rq = $returnable[$it->id] ?? ['qty' => $it->qty, 'left' => 0]; @endphp
                                        <tr>
                                            <td>{{ $it->title_snapshot }}<div class="text-muted">{{ number_format($it->unit_price) }}원</div></td>
                                            <td class="text-end">{{ $it->qty }}</td>
                                            <td class="text-end {{ $rq['left'] > 0 ? 'fw-bold' : 'text-muted' }}">{{ $rq['left'] }}</td>
                                            <td>
                                                <input type="number" name="items[{{ $it->id }}]" class="form-control form-control-sm text-end"
                                                       min="0" max="{{ $rq['left'] }}" value="0" {{ $rq['left'] <= 0 ? 'disabled' : '' }}>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @if(isset($returnPayments) && $returnPayments->count())
                                {{-- 소매 — 어느 학부모 결제에서 환불할지. 안 고르면 환불 여지가 남은 결제부터 순서대로 --}}
                                <div class="mb-2">
                                    <label class="form-label small fw-bold navy mb-1">환불 대상 학부모 (소매)</label>
                                    <select name="payment_request_id" class="form-select form-select-sm">
                                        <option value="">지정 안 함 — 결제 순서대로 환불</option>
                                        @foreach($returnPayments as $rp)
                                            <option value="{{ $rp->id }}">
                                                {{ $rp->parent_name }}@if($rp->student_name) ({{ $rp->student_name }})@endif
                                                — {{ number_format($rp->amount - $rp->refunded_amount) }}원 환불 가능
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            <div class="row g-2">
                                <div class="col-5">
                                    <label class="form-label small fw-bold navy mb-1">반품 사유</label>
                                    <select name="reason_code" class="form-select form-select-sm">
                                        @foreach($returnReasons as $k => $label)
                                            <option value="{{ $k }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-7">
                                    <label class="form-label small fw-bold navy mb-1">상세 (선택)</label>
                                    <input type="text" name="reason_text" class="form-control form-control-sm" maxlength="500"
                                           placeholder="예: 표지 파손 3권">
                                </div>
                            </div>
                            <div class="alert alert-light border small text-muted mt-3 mb-0">
                                <i class="bi bi-info-circle"></i>
                                총판이 확정하면 결제된 주문은 반품액만큼 <strong>PG 부분취소로 자동 환불</strong>됩니다.
                                아직 결제 전이면 장부에서만 차감됩니다.
                            </div>
                        </div>
                        <div class="modal-footer py-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">닫기</button>
                            <button class="btn btn-sm btn-primary"><i class="bi bi-check-lg"></i> 반품 접수</button>
                        </div>
                    </form>
                </div>
            </div>
            @endif
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

    {{-- 우측: 주문 목록 + 상태 로그 + 결제 액션(하단) --}}
    <div class="col-lg-7">
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

        {{-- 학원 결제 액션 (우측 하단) --}}
        @if($user->role_code === 'academy' && in_array($order->status_code, ['requested','confirmed','accepted','shipped']))
            <div class="card section-card mt-3 border-warning">
                <div class="card-body">
                    @if(($vendor->trade_type ?? 'retail') === 'wholesale')
                        {{-- 도매: 학원이 직접 결제 (학부모 거치지 않음) --}}
                        @if($portOneActive ?? false)
                            <div class="d-grid gap-2">
                                @foreach($portOneMethods as $m)
                                    <button type="button" class="pay-direct-btn btn btn-warning w-100 text-dark fw-bold"
                                            data-amount="{{ (int) $order->total_amount }}"
                                            data-order-name="교재비 · 주문 {{ $order->order_no }}"
                                            data-channel-key="{{ $m['channelKey'] }}" data-pay-method="{{ $m['payMethod'] }}">
                                        <i class="bi bi-{{ $m['icon'] }}"></i> 교재비 {{ $m['label'] }} 결제 ({{ number_format($order->total_amount) }}원)
                                    </button>
                                @endforeach
                            </div>
                        @else
                            <form method="POST" action="{{ route('my.orders.pay_direct', $order->id) }}"
                                  onsubmit="return confirm('교재비 {{ number_format($order->total_amount) }}원을 결제할까요? (테스트)')">
                                @csrf
                                <button class="btn btn-warning w-100 text-dark fw-bold">
                                    <i class="bi bi-credit-card"></i> 교재비 결제 ({{ number_format($order->total_amount) }}원)
                                </button>
                            </form>
                        @endif
                    @else
                        {{-- 소매: 학부모에게 결제 요청 --}}
                        <a href="{{ route('my.orders.payment.create', $order->id) }}" class="btn btn-warning w-100">
                            <i class="bi bi-chat-dots-fill"></i> 학부모에게 결제 요청
                        </a>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
@push('scripts')
@if(($portOneActive ?? false) && ($vendor->trade_type ?? 'retail') === 'wholesale')
<script src="https://cdn.portone.io/v2/browser-sdk.js"></script>
<script>
(function () {
    var btns = document.querySelectorAll('.pay-direct-btn');
    if (!btns.length) return;
    var csrf      = '{{ csrf_token() }}';
    var storeId   = {!! json_encode($portOneStoreId ?? '') !!};
    var verifyUrl = '{{ route('my.orders.pay_direct_verify', $order->id) }}';
    var customer  = {
        fullName:    {!! json_encode($user->name ?? ($vendor->name ?? '학원')) !!},
        phoneNumber: {!! json_encode($user->phone ?? '') !!},
        email:       {!! json_encode(filter_var($user->email ?? '', FILTER_VALIDATE_EMAIL) ? $user->email : setting('company_email', 'help@booksys.co.kr')) !!},
    };

    // 모바일 리다이렉트 복귀 처리 (결제 후 ?portone_return=1&paymentId=... 로 돌아옴)
    (function () {
        var q = new URLSearchParams(window.location.search);
        if (!q.has('portone_return')) return;
        var paymentId = q.get('paymentId'), code = q.get('code'), message = q.get('message');
        history.replaceState(null, '', window.location.pathname);
        if (code) {
            if (!String(code).includes('CANCEL')) alert('결제 실패: ' + (message || code));
            return;
        }
        if (!paymentId) return;
        fetch(verifyUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify({ payment_id: paymentId }),
        })
        .then(function (r) { return r.json(); })
        .then(function (j) {
            if (j.success) window.location.href = j.redirect_url || window.location.pathname;
            else alert(j.message || '결제 검증 실패');
        })
        .catch(function () { alert('결제 확인 중 오류가 발생했습니다. 잠시 후 새로고침해 주세요.'); });
    })();

    btns.forEach(function (btn) {
        var orig = btn.innerHTML;
        btn.addEventListener('click', async function () {
            btns.forEach(function (b) { b.disabled = true; });
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i> 결제 요청 중...';
            var paymentId = 'order-{{ $order->id }}-' + Date.now();
            try {
                var response = await PortOne.requestPayment({
                    storeId: storeId,
                    channelKey: btn.dataset.channelKey,
                    paymentId: paymentId,
                    orderName: btn.dataset.orderName,
                    totalAmount: parseInt(btn.dataset.amount, 10),
                    currency: 'CURRENCY_KRW',
                    payMethod: btn.dataset.payMethod,
                    customer: customer,
                    // 모바일 리다이렉트 방식 필수 (미지정 시 모바일 결제창 미표시)
                    redirectUrl: window.location.origin + window.location.pathname + '?portone_return=1',
                });
                if (response && response.code != null) {
                    if (!String(response.code).includes('CANCEL')) alert('결제 실패: ' + (response.message || response.code));
                    btns.forEach(function (b) { b.disabled = false; }); btn.innerHTML = orig; return;
                }
                var r = await fetch(verifyUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: JSON.stringify({ payment_id: response.paymentId }),
                });
                var j = await r.json();
                if (j.success) {
                    window.location.href = j.redirect_url || window.location.href;
                } else {
                    alert(j.message || '결제 검증 실패');
                    btns.forEach(function (b) { b.disabled = false; }); btn.innerHTML = orig;
                }
            } catch (e) {
                console.error('payDirect 오류:', e);
                alert('결제 중 오류가 발생했습니다. 잠시 후 다시 시도해주세요.');
                btns.forEach(function (b) { b.disabled = false; }); btn.innerHTML = orig;
            }
        });
    });
})();
</script>
@endif
@endpush
@endsection
