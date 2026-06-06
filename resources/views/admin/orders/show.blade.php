@extends('admin.layouts.admin')
@section('title', '주문 · ' . $order->order_no)

@section('content')
<div class="page-header">
    <div>
        <a href="{{ route('admin.orders.index') }}" class="text-muted small text-decoration-none">
            <i class="bi bi-arrow-left"></i> 주문 목록
        </a>
        <h1 class="h4 mb-0 mt-1">
            <code>{{ $order->order_no }}</code>
            @switch($order->status_code)
                @case('requested') <span class="badge bg-warning text-dark ms-2">접수</span> @break
                @case('confirmed') <span class="badge bg-info ms-2">영업자확정</span> @break
                @case('accepted')  <span class="badge bg-primary ms-2">총판접수</span> @break
                @case('shipped')   <span class="badge bg-success ms-2">출고</span> @break
                @case('in_transit')<span class="badge bg-success ms-2">배송중</span> @break
                @case('completed') <span class="badge bg-dark ms-2">완료</span> @break
                @case('canceled')  <span class="badge bg-secondary ms-2">취소</span> @break
                @case('returned')  <span class="badge bg-danger ms-2">반품</span> @break
            @endswitch
        </h1>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="row g-3">
    <div class="col-lg-8">
        {{-- 주문 라인 --}}
        <div class="card section-card mb-3">
            <div class="card-header"><strong>주문 라인 ({{ $items->count() }}건)</strong></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:50px;"></th>
                            <th>도서</th>
                            <th class="text-end">수량</th>
                            <th class="text-end">정가</th>
                            <th class="text-end">할인율</th>
                            <th class="text-end">단가</th>
                            <th class="text-end">합계</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $it)
                            <tr>
                                <td>
                                    @if($it->cover_path)
                                        <img src="{{ str_starts_with($it->cover_path, 'http') ? $it->cover_path : asset('storage/'.$it->cover_path) }}" style="height:36px;border-radius:3px">
                                    @else
                                        <i class="bi bi-book text-muted"></i>
                                    @endif
                                </td>
                                <td class="small">
                                    {{ $it->title_snapshot ?: $it->book_title }}
                                    <div class="text-muted"><code>{{ $it->isbn_snapshot ?: $it->book_isbn }}</code></div>
                                </td>
                                <td class="text-end">{{ number_format($it->qty) }}</td>
                                <td class="text-end">{{ number_format($it->list_price) }}</td>
                                <td class="text-end">{{ rtrim(rtrim($it->discount_rate, '0'), '.') }}%</td>
                                <td class="text-end">{{ number_format($it->unit_price) }}</td>
                                <td class="text-end fw-bold">{{ number_format($it->line_total) }}원</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6" class="text-end">소계</td>
                            <td class="text-end">{{ number_format($order->subtotal_amount) }}원</td>
                        </tr>
                        <tr>
                            <td colspan="6" class="text-end">배송비</td>
                            <td class="text-end">{{ number_format($order->shipping_fee) }}원</td>
                        </tr>
                        <tr class="table-light">
                            <td colspan="6" class="text-end fw-bold">총액</td>
                            <td class="text-end fw-bold">{{ number_format($order->total_amount) }}원</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- 상태 이력 --}}
        <div class="card section-card mb-3">
            <div class="card-header"><strong><i class="bi bi-clock-history"></i> 상태 이력</strong></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>변경</th><th>처리자</th><th>일시</th><th>사유</th></tr></thead>
                    <tbody>
                        @foreach($statusLogs as $log)
                            <tr>
                                <td>
                                    @if($log->from_status)<span class="text-muted small">{{ $statusLabels[$log->from_status] ?? $log->from_status }} →</span>@endif
                                    <strong>{{ $statusLabels[$log->to_status] ?? $log->to_status }}</strong>
                                </td>
                                <td class="small">{{ $log->changed_by_name ?: '시스템' }}</td>
                                <td class="text-muted small">{{ \Carbon\Carbon::parse($log->created_at)->format('Y-m-d H:i:s') }}</td>
                                <td class="text-muted small">{{ $log->reason }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        {{-- 거래처/영업자/총판 --}}
        <div class="card section-card mb-3">
            <div class="card-header"><strong><i class="bi bi-people"></i> 관련 사용자</strong></div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-4 text-muted">거래처</dt>
                    <dd class="col-8">
                        @if($vendor)<a href="{{ route('admin.vendors.show', $vendor->id) }}">{{ $vendor->name }}</a>@endif
                    </dd>
                    <dt class="col-4 text-muted">영업자</dt>
                    <dd class="col-8">
                        @if($agent)<a href="{{ route('admin.users.show', $agent->id) }}">{{ $agent->name }}</a>@endif
                    </dd>
                    <dt class="col-4 text-muted">총판</dt>
                    <dd class="col-8">
                        @if($dist)<a href="{{ route('admin.users.show', $dist->id) }}">{{ $dist->name }}</a>@else<span class="text-muted">미배정</span>@endif
                    </dd>
                    <dt class="col-4 text-muted">배송지</dt>
                    <dd class="col-8">{{ $order->ship_to_address }} {{ $order->ship_to_address_detail }}</dd>
                    <dt class="col-4 text-muted">수령인</dt>
                    <dd class="col-8">{{ $order->ship_to_contact }}</dd>
                </dl>
            </div>
        </div>

        {{-- 배송 --}}
        <div class="card section-card mb-3">
            <div class="card-header"><strong><i class="bi bi-truck"></i> 배송 정보</strong></div>
            <div class="card-body">
                @if($shipment)
                    <dl class="row mb-0 small">
                        <dt class="col-4 text-muted">택배사</dt>
                        <dd class="col-8">{{ $shipment->courier_code }}</dd>
                        <dt class="col-4 text-muted">송장번호</dt>
                        <dd class="col-8"><code>{{ $shipment->tracking_no }}</code></dd>
                        <dt class="col-4 text-muted">상태</dt>
                        <dd class="col-8"><span class="badge bg-light text-dark">{{ $shipment->ship_status_code }}</span></dd>
                        @if($shipment->shipped_at)
                            <dt class="col-4 text-muted">출고일시</dt>
                            <dd class="col-8 small">{{ \Carbon\Carbon::parse($shipment->shipped_at)->format('Y-m-d H:i') }}</dd>
                        @endif
                        @if($shipment->delivered_at)
                            <dt class="col-4 text-muted">배송완료</dt>
                            <dd class="col-8 small">{{ \Carbon\Carbon::parse($shipment->delivered_at)->format('Y-m-d H:i') }}</dd>
                        @endif
                    </dl>
                @elseif($order->status_code === 'accepted')
                    <form method="POST" action="{{ route('admin.orders.ship', $order) }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label small text-muted">택배사</label>
                            <select name="courier_code" class="form-select form-select-sm" required>
                                @foreach($courierOptions as $c)
                                    <option value="{{ $c->code }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-muted">송장번호</label>
                            <input type="text" name="tracking_no" class="form-control form-control-sm" required>
                        </div>
                        <button class="btn btn-sm btn-primary w-100"><i class="bi bi-truck"></i> 송장 입력 + 출고처리</button>
                    </form>
                @else
                    <div class="text-muted text-center py-2 small">배송 정보가 없습니다.</div>
                @endif
            </div>
        </div>

        {{-- 상태 전이 --}}
        @if(! empty($nextStates))
            <div class="card section-card">
                <div class="card-header"><strong><i class="bi bi-arrow-right-circle"></i> 상태 변경</strong></div>
                <div class="card-body">
                    @foreach($nextStates as $next)
                        @php
                            $label = $statusLabels[$next] ?? $next;
                            $btnClass = match($next) {
                                'confirmed' => 'btn-info',
                                'accepted'  => 'btn-primary',
                                'completed' => 'btn-dark',
                                'canceled'  => 'btn-outline-danger',
                                'returned'  => 'btn-outline-warning',
                                default     => 'btn-outline-secondary',
                            };
                            $skipShip = ($order->status_code === 'accepted' && $next === 'shipped');
                        @endphp
                        @if(! $skipShip)
                            <form method="POST" action="{{ route('admin.orders.transition', $order) }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="to_status" value="{{ $next }}">
                                <button class="btn btn-sm {{ $btnClass }} mb-1"
                                        onclick="return confirm('{{ $label }}로 변경할까요?')">
                                    {{ $label }}
                                </button>
                            </form>
                        @endif
                    @endforeach
                    @if(in_array('shipped', $nextStates, true) && $order->status_code === 'accepted')
                        <div class="text-muted small mt-2">출고는 좌측 "송장 입력" 폼에서 진행</div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
