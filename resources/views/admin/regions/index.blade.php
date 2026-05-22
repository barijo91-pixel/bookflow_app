@extends('admin.layouts.admin')
@section('title', '지역 관리')

@section('content')
<div class="page-header">
    <h1 class="h4 mb-0">지역 관리 <small class="text-muted fs-6">시도 {{ $sidos->count() }}개 · 시군구 {{ $sidos->sum('sg_count') }}개</small></h1>
    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#sidoAddModal">
        <i class="bi bi-plus-lg"></i> 시·도 추가
    </button>
</div>

<div class="row g-3">
    {{-- LEFT: 시도 목록 --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><strong>시·도</strong></div>
            <div class="list-group list-group-flush" style="max-height:600px; overflow-y:auto;">
                @foreach($sidos as $sido)
                    <a href="{{ route('admin.regions.index', ['sido' => $sido->id]) }}"
                       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center
                              {{ $sidoId == $sido->id ? 'active' : '' }}">
                        <div>
                            {{ $sido->name }}
                            @if(! $sido->is_active)<span class="badge bg-secondary ms-1">OFF</span>@endif
                        </div>
                        <small class="{{ $sidoId == $sido->id ? '' : 'text-muted' }}">{{ $sido->sg_count }}개</small>
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    {{-- RIGHT: 시군구 목록 --}}
    <div class="col-lg-8">
        @if(! $selectedSido)
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center text-muted py-5">
                    <i class="bi bi-geo-alt" style="font-size:2.5rem"></i>
                    <p class="mt-3 mb-0">왼쪽에서 시·도를 선택하면 하위 시·군·구가 표시됩니다.</p>
                </div>
            </div>
        @else
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong>{{ $selectedSido->name }} ▸ 시·군·구 ({{ $sigungus->count() }}개)</strong>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                data-bs-toggle="modal" data-bs-target="#sidoEditModal-{{ $selectedSido->id }}">
                            <i class="bi bi-pencil"></i> 시·도 수정
                        </button>
                        <button type="button" class="btn btn-sm btn-primary"
                                data-bs-toggle="modal" data-bs-target="#sigunguAddModal">
                            <i class="bi bi-plus-lg"></i> 시·군·구 추가
                        </button>
                    </div>
                </div>
                <form method="GET" class="card-body py-2 border-bottom">
                    <input type="hidden" name="sido" value="{{ $sidoId }}">
                    <div class="d-flex gap-2">
                        <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="시·군·구 검색">
                        <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>이름</th>
                                <th>코드</th>
                                <th class="text-center">정렬</th>
                                <th class="text-center">활성</th>
                                <th class="text-end">조치</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($sigungus as $sg)
                                <tr>
                                    <td class="small text-muted">{{ $sg->id }}</td>
                                    <td>{{ $sg->name }}</td>
                                    <td class="small text-muted">{{ $sg->code }}</td>
                                    <td class="text-center small">{{ $sg->sort_order }}</td>
                                    <td class="text-center">
                                        @if($sg->is_active)<span class="badge bg-success">ON</span>
                                        @else<span class="badge bg-secondary">OFF</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-link p-0"
                                                data-bs-toggle="modal" data-bs-target="#sgEditModal-{{ $sg->id }}">수정</button>
                                        <form method="POST" action="{{ route('admin.regions.destroy', $sg->id) }}" class="d-inline"
                                              onsubmit="return confirm('정말 삭제할까요?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-link text-danger p-0">삭제</button>
                                        </form>
                                    </td>
                                </tr>

                                {{-- Edit modal for sigungu --}}
                                <div class="modal fade" id="sgEditModal-{{ $sg->id }}" tabindex="-1">
                                    <div class="modal-dialog">
                                        <form method="POST" action="{{ route('admin.regions.update', $sg->id) }}" class="modal-content">
                                            @csrf @method('PUT')
                                            <div class="modal-header"><h5 class="modal-title">시·군·구 수정 · {{ $sg->name }}</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
                                            <div class="modal-body">
                                                <div class="mb-2"><label class="form-label small text-muted">이름</label>
                                                    <input type="text" name="name" value="{{ $sg->name }}" class="form-control" required></div>
                                                <div class="mb-2"><label class="form-label small text-muted">코드(법정동/행정동)</label>
                                                    <input type="text" name="code" value="{{ $sg->code }}" class="form-control"></div>
                                                <div class="row g-2">
                                                    <div class="col-6"><label class="form-label small text-muted">정렬</label>
                                                        <input type="number" name="sort_order" value="{{ $sg->sort_order }}" class="form-control"></div>
                                                    <div class="col-6 d-flex align-items-end">
                                                        <div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="ac-{{ $sg->id }}" @checked($sg->is_active)><label for="ac-{{ $sg->id }}" class="form-check-label">활성</label></div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal" type="button">취소</button><button class="btn btn-primary">저장</button></div>
                                        </form>
                                    </div>
                                </div>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-4">시·군·구가 없습니다.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Sido Edit Modal --}}
            <div class="modal fade" id="sidoEditModal-{{ $selectedSido->id }}" tabindex="-1">
                <div class="modal-dialog">
                    <form method="POST" action="{{ route('admin.regions.update', $selectedSido->id) }}" class="modal-content">
                        @csrf @method('PUT')
                        <div class="modal-header"><h5 class="modal-title">시·도 수정 · {{ $selectedSido->name }}</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">
                            <div class="mb-2"><label class="form-label small text-muted">이름</label>
                                <input type="text" name="name" value="{{ $selectedSido->name }}" class="form-control" required></div>
                            <div class="mb-2"><label class="form-label small text-muted">코드</label>
                                <input type="text" name="code" value="{{ $selectedSido->code }}" class="form-control"></div>
                            <div class="row g-2">
                                <div class="col-6"><label class="form-label small text-muted">정렬</label>
                                    <input type="number" name="sort_order" value="{{ $selectedSido->sort_order }}" class="form-control"></div>
                                <div class="col-6 d-flex align-items-end">
                                    <div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="sd-{{ $selectedSido->id }}" @checked($selectedSido->is_active)><label for="sd-{{ $selectedSido->id }}" class="form-check-label">활성</label></div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <form method="POST" action="{{ route('admin.regions.destroy', $selectedSido->id) }}" onsubmit="return confirm('시·도를 삭제할까요?')">@csrf @method('DELETE')<button class="btn btn-outline-danger" type="submit">삭제</button></form>
                            <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">취소</button>
                            <button class="btn btn-primary">저장</button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Sigungu Add Modal --}}
            <div class="modal fade" id="sigunguAddModal" tabindex="-1">
                <div class="modal-dialog">
                    <form method="POST" action="{{ route('admin.regions.store') }}" class="modal-content">
                        @csrf
                        <input type="hidden" name="level" value="sigungu">
                        <input type="hidden" name="parent_id" value="{{ $selectedSido->id }}">
                        <div class="modal-header"><h5 class="modal-title">{{ $selectedSido->name }} ▸ 시·군·구 추가</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">
                            <div class="mb-2"><label class="form-label small text-muted">이름</label>
                                <input type="text" name="name" class="form-control" required placeholder="예: 강남구"></div>
                            <div class="mb-2"><label class="form-label small text-muted">코드(법정동 코드)</label>
                                <input type="text" name="code" class="form-control"></div>
                            <div class="mb-2"><label class="form-label small text-muted">정렬</label>
                                <input type="number" name="sort_order" value="999" class="form-control"></div>
                        </div>
                        <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal" type="button">취소</button><button class="btn btn-primary">추가</button></div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>

{{-- Sido Add Modal --}}
<div class="modal fade" id="sidoAddModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.regions.store') }}" class="modal-content">
            @csrf
            <input type="hidden" name="level" value="sido">
            <div class="modal-header"><h5 class="modal-title">시·도 추가</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-2"><label class="form-label small text-muted">이름</label>
                    <input type="text" name="name" class="form-control" required placeholder="예: 서울특별시"></div>
                <div class="mb-2"><label class="form-label small text-muted">정렬</label>
                    <input type="number" name="sort_order" value="999" class="form-control"></div>
            </div>
            <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal" type="button">취소</button><button class="btn btn-primary">추가</button></div>
        </form>
    </div>
</div>
@endsection
