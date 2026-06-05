@extends('admin.layouts.admin')
@section('title', '코드 그룹')

@section('content')
<div class="page-header">
    <h1 class="h4 mb-0">코드 테이블 · 그룹 목록</h1>
    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#groupCreateModal">
        <i class="bi bi-plus-lg"></i> 그룹 추가
    </button>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-row-highlight">
            <thead class="table-light">
                <tr>
                    <th style="width:60px;">#</th>
                    <th style="width:180px;">그룹코드</th>
                    <th>이름</th>
                    <th>설명</th>
                    <th class="text-center" style="width:80px;">코드수</th>
                    <th class="text-center" style="width:80px;">순서</th>
                    <th class="text-center" style="width:80px;">활성</th>
                    <th class="text-end" style="width:240px;">조치</th>
                </tr>
            </thead>
            <tbody>
                @forelse($groups as $g)
                    <tr>
                        <td>{{ $g->id }}</td>
                        <td>
                            <code>{{ $g->group_code }}</code>
                            @if($g->is_system)<span class="badge bg-info ms-1">SYS</span>@endif
                        </td>
                        <td>{{ $g->name }}</td>
                        <td class="text-muted small">{{ $g->description }}</td>
                        <td class="text-center">{{ $g->codes_count }}</td>
                        <td class="text-center">{{ $g->sort_order }}</td>
                        <td class="text-center">
                            @if($g->is_active)
                                <span class="badge bg-success">ON</span>
                            @else
                                <span class="badge bg-secondary">OFF</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.codes.index', $g->group_code) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-list-ul"></i> 코드
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#groupEditModal-{{ $g->id }}">
                                수정
                            </button>
                            @if(! $g->is_system)
                                <form method="POST" action="{{ route('admin.code-groups.destroy', $g->group_code) }}"
                                      class="d-inline" onsubmit="return confirm('정말 삭제할까요?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">삭제</button>
                                </form>
                            @endif
                        </td>
                    </tr>

                    {{-- Edit modal --}}
                    <div class="modal fade" id="groupEditModal-{{ $g->id }}" tabindex="-1">
                        <div class="modal-dialog">
                            <form method="POST" action="{{ route('admin.code-groups.update', $g->group_code) }}" class="modal-content">
                                @csrf @method('PUT')
                                <div class="modal-header">
                                    <h5 class="modal-title">코드 그룹 수정 · <code>{{ $g->group_code }}</code></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label">이름</label>
                                        <input type="text" name="name" value="{{ $g->name }}" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">설명</label>
                                        <input type="text" name="description" value="{{ $g->description }}" class="form-control">
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <label class="form-label">정렬</label>
                                            <input type="number" name="sort_order" value="{{ $g->sort_order }}" class="form-control">
                                        </div>
                                        <div class="col-6 d-flex align-items-end">
                                            <div class="form-check">
                                                <input type="checkbox" name="is_active" id="active-{{ $g->id }}"
                                                    class="form-check-input" value="1" @checked($g->is_active)>
                                                <label class="form-check-label" for="active-{{ $g->id }}">활성</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                                    <button class="btn btn-primary">저장</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">데이터가 없습니다.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Create modal --}}
<div class="modal fade" id="groupCreateModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.code-groups.store') }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">코드 그룹 추가</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">그룹코드 (영문/숫자/-_, 50자 이내)</label>
                    <input type="text" name="group_code" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">이름</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">설명</label>
                    <input type="text" name="description" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">정렬</label>
                    <input type="number" name="sort_order" class="form-control" value="999">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                <button class="btn btn-primary">추가</button>
            </div>
        </form>
    </div>
</div>
@endsection
