@extends('public.layouts.app')
@section('title', '주문 #'.$order->order_no)

@section('content')
@php
    $statusOptions = [
        'requested'  => ['접수', 'bg-warning text-dark'],
        'confirmed'  => ['영업자 확정', 'bg-info'],
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
                    <dd class="col-8">{{ $vendor->name ?? '-' }}</dd>
                    <dt class="col-4 text-muted">영업자</dt>
                    <dd class="col-8">{{ $agent->name ?? '-' }}</dd>
                    <dt class="col-4 text-muted">총판</dt>
                    <dd class="col-8">{{ $dist->name ?? '(미배정)' }}</dd>
                    <dt class="col-4 text-muted">배송 방식</dt>
                    <dd class="col-8">
                        @if(($order->delivery_type ?? 'parcel') === 'direct')
                            <span class="badge bg-warning text-dark">직접 배송</span>
                        @else
                            <span class="badge bg-light text-dark">택배</span>
                        @endif
                    </dd>
                    <dt class="col-4 text-muted">소계</dt>
                    <dd class="col-8 text-end">{{ number_format($order->subtotal_amount) }}원</dd>
                    <dt class="col-4 text-muted">배송비</dt>
                    <dd class="col-8 text-end">{{ number_format($order->shipping_fee) }}원</dd>
                    <dt class="col-4 text-muted fw-bold">총액</dt>
                    <dd class="col-8 text-end fw-bold navy">{{ number_format($order->total_amount) }}원</dd>
                </dl>
            </div>
        </div>

        {{-- 액션 카드 --}}
        @php $isAcademy = $user->role_code === 'academy'; @endphp
        @if($canConfirm || $canAccept || $canShip || $canCancel || $canEdit || $isAcademy)
            <div class="card section-card mb-3">
                <div class="card-header"><strong><i class="bi bi-lightning"></i> 처리</strong></div>
                <div class="card-body">
                    @if($canEdit)
                        <a href="{{ route('my.orders.edit', $order->id) }}" class="btn btn-outline-primary w-100 mb-2">
                            <i class="bi bi-pencil-square"></i> 주문 수정 (수량/도서 삭제)
                        </a>
                    @endif
                    @if($user->role_code === 'academy' && in_array($order->status_code, ['requested','confirmed','accepted','shipped']))
                        <a href="{{ route('my.orders.payment.create', $order->id) }}" class="btn btn-warning w-100 mb-2">
                            <i class="bi bi-chat-dots-fill"></i> 학부모에게 결제 요청
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
                                <i class="bi bi-check-lg"></i> 영업자 확정
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
                        <form method="POST" action="{{ route('my.orders.ship', $order->id) }}" class="mb-2">
                            @csrf
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
                        </form>
                    @endif

                    @if($canCancel)
                        <form method="POST" action="{{ route('my.orders.transition', $order->id) }}"
                              onsubmit="return confirm('주문을 취소하시겠습니까? 되돌릴 수 없습니다.')">
                            @csrf
                            <input type="hidden" name="to_status" value="canceled">
                            <div class="mb-2">
                                <label class="form-label small text-muted mb-1">취소 사유 (선택)</label>
                                <input type="text" name="reason" class="form-control form-control-sm" maxlength="500">
                            </div>
                            <button class="btn btn-outline-danger btn-sm w-100">
                                <i class="bi bi-x-lg"></i> 주문 취소
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @endif

        {{-- 출고 정보 --}}
        @if($shipment)
            <div class="card section-card mb-3">
                <div class="card-header"><strong><i class="bi bi-truck"></i> 출고/배송</strong></div>
                <div class="card-body small">
                    <dl class="row mb-0">
                        <dt class="col-4 text-muted">택배사</dt>
                        <dd class="col-8">{{ $shipment->courier_code }}</dd>
                        <dt class="col-4 text-muted">송장번호</dt>
                        <dd class="col-8"><code>{{ $shipment->tracking_no }}</code></dd>
                        <dt class="col-4 text-muted">출고일</dt>
                        <dd class="col-8">{{ $shipment->shipped_at ? \Carbon\Carbon::parse($shipment->shipped_at)->format('Y-m-d H:i') : '-' }}</dd>
                    </dl>
                </div>
            </div>
        @endif
    </div>

    {{-- 우측: 도서 목록 + 상태 로그 --}}
    <div class="col-lg-7">
        <div class="card section-card mb-3">
            <div class="card-header"><strong><i class="bi bi-book"></i> 도서 목록 ({{ $items->count() }}건)</strong></div>
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

        <div class="card section-card">
            <div class="card-header"><strong><i class="bi bi-clock-history"></i> 상태 이력</strong></div>
            <div class="card-body">
                @if($statusLogs->isEmpty())
                    <div class="text-muted small">아직 이력이 없습니다.</div>
                @else
                    <ul class="list-unstyled mb-0 small">
                        @foreach($statusLogs as $log)
                            <li class="mb-2">
                                <strong>{{ $log->from_status }}</strong> → <strong>{{ $log->to_status }}</strong>
                                <span class="text-muted">by {{ $log->changed_by_name ?? '시스템' }}</span>
                                <span class="text-muted">· {{ \Carbon\Carbon::parse($log->created_at)->format('m-d H:i') }}</span>
                                @if($log->reason)<div class="text-muted small">{{ $log->reason }}</div>@endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
