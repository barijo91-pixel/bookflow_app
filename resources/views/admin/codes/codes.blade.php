@extends('admin.layouts.admin')
@section('title', '코드 · ' . $group->name)

@section('content')
<div class="page-header">
    <div>
        <a href="{{ route('admin.code-groups.index') }}" class="text-muted small text-decoration-none">
            <i class="bi bi-arrow-left"></i> 그룹 목록
        </a>
        <h1 class="h4 mb-0 mt-1">{{ $group->name }} <small class="text-muted"><code>{{ $group->group_code }}</code></small></h1>
    </div>
    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#codeCreateModal">
        <i class="bi bi-plus-lg"></i> 코드 추가
    </button>
</div>

<div class="card section-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-row-highlight">
            <thead class="table-light">
                <tr>
                    <th style="width:60px;">#</th>
                    <th style="width:180px;">코드</th>
                    <th>이름</th>
                    <th>부가값</th>
                    <th class="text-center" style="width:80px;">정렬</th>
                    <th class="text-center" style="width:80px;">활성</th>
                    <th class="text-end" style="width:200px;">조치</th>
                </tr>
            </thead>
            <tbody>
                @forelse($codes as $c)
                    <tr>
                        <td>{{ $c->id }}</td>
                        <td><code>{{ $c->code }}</code></td>
                        <td>{{ $c->name }}</td>
                        <td class="text-muted small">{{ $c->value }}</td>
                        <td class="text-center">{{ $c->sort_order }}</td>
                        <td class="text-center">
                            @if($c->is_active)
                                <span class="badge bg-success">ON</span>
                            @else
                                <span class="badge bg-secondary">OFF</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                    data-bs-toggle="modal" data-bs-target="#codeEditModal-{{ $c->id }}">
                                수정
                            </button>
                            <form method="POST" action="{{ route('admin.codes.destroy', [$group->group_code, $c->id]) }}"
                                  class="d-inline" onsubmit="return confirm('정말 삭제할까요?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">삭제</button>
                            </form>
                        </td>
                    </tr>

                    <div class="modal fade" id="codeEditModal-{{ $c->id }}" tabindex="-1">
                        <div class="modal-dialog">
                            <form method="POST" action="{{ route('admin.codes.update', [$group->group_code, $c->id]) }}" class="modal-content">
                                @csrf @method('PUT')
                                <div class="modal-header">
                                    <h5 class="modal-title">코드 수정 · <code>{{ $c->code }}</code></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label">이름</label>
                                        <input type="text" name="name" value="{{ $c->name }}" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">부가값</label>
                                        <input type="text" name="value" value="{{ $c->value }}" class="form-control">
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <label class="form-label">정렬</label>
                                            <input type="number" name="sort_order" value="{{ $c->sort_order }}" class="form-control">
                                        </div>
                                        <div class="col-6 d-flex align-items-end">
                                            <div class="form-check">
                                                <input type="checkbox" name="is_active" id="cactive-{{ $c->id }}"
                                                       class="form-check-input" value="1" @checked($c->is_active)>
                                                <label class="form-check-label" for="cactive-{{ $c->id }}">활성</label>
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
                    <tr><td colspan="7" class="text-center text-muted py-4">코드가 없습니다.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="codeCreateModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.codes.store', $group->group_code) }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">코드 추가 · <code>{{ $group->group_code }}</code></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">코드</label>
                    <input type="text" name="code" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">이름</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">부가값</label>
                    <input type="text" name="value" class="form-control">
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
