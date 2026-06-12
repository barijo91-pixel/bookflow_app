{{-- 체크리스트 공통 아이템 렌더링 --}}
<ul class="list-unstyled mb-0">
    @foreach($items as $item)
        <li class="d-flex align-items-start py-2 {{ ! $loop->last ? 'border-bottom' : '' }}">
            <div class="me-3" style="font-size:1.4rem;">
                @if($item['done'])
                    <i class="bi bi-check-circle-fill text-success"></i>
                @else
                    <i class="bi bi-circle text-muted"></i>
                @endif
            </div>
            <div class="flex-grow-1">
                <div class="{{ $item['done'] ? 'text-muted text-decoration-line-through' : 'fw-bold' }}">
                    {{ $item['label'] }}
                </div>
                <div class="small text-muted">{{ $item['desc'] }}</div>
            </div>
            @if(! $item['done'])
                <a href="{{ $item['href'] }}" class="btn btn-sm btn-outline-primary">설정</a>
            @endif
        </li>
    @endforeach
</ul>
