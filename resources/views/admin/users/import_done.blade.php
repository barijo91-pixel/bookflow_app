@extends('admin.layouts.admin')
@section('title', '사용자 일괄 등록 완료')

@section('content')
<div class="page-header">
    <div>
        <h1 class="h4 mb-0"><i class="bi bi-check-circle text-success"></i> 일괄 등록 완료</h1>
        <p class="text-muted small mb-0">Job #{{ $jobId }}</p>
    </div>
    <div>
        <a href="{{ route('admin.users.import.show') }}" class="btn btn-sm btn-outline-secondary">새 업로드</a>
        <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-navy">사용자 목록으로</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card section-card">
            <div class="card-body py-3">
                <div class="small text-muted">등록 성공</div>
                <div class="h3 mb-0 text-success">{{ $result['success'] }}건</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card section-card">
            <div class="card-body py-3">
                <div class="small text-muted">실패</div>
                <div class="h3 mb-0 {{ $result['failed'] > 0 ? 'text-danger' : '' }}">{{ $result['failed'] }}건</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card section-card">
            <div class="card-body py-3">
                <div class="small text-muted">처리 일시</div>
                <div class="h6 mb-0 text-muted">{{ now()->format('Y-m-d H:i:s') }}</div>
            </div>
        </div>
    </div>
</div>

@if(! empty($result['errors']))
    <div class="card section-card mb-3">
        <div class="card-header bg-danger text-white"><strong>실패 항목</strong></div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>행</th><th>오류</th></tr></thead>
                <tbody>
                    @foreach($result['errors'] as $err)
                        <tr><td>{{ $err['row'] }}</td><td class="small text-danger">{{ $err['msg'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

@if(! empty($createdUsers))
    <div class="card section-card">
        <div class="card-header bg-warning d-flex justify-content-between align-items-center">
            <strong><i class="bi bi-key"></i> 등록된 사용자 + 초기 비밀번호 (1회만 표시)</strong>
            <button class="btn btn-sm btn-light" onclick="copyTable()">
                <i class="bi bi-clipboard"></i> 표 전체 복사
            </button>
        </div>
        <div class="card-body bg-warning-subtle">
            <p class="small mb-2">
                <strong>⚠️ 이 비밀번호는 지금만 표시됩니다.</strong>
                각 사용자에게 안전하게 전달하세요. (첫 로그인 시 변경 강제됨)
            </p>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0" id="createdUsersTable">
                <thead class="table-light"><tr>
                    <th>아이디</th><th>이름</th><th>휴대폰</th><th>역할</th><th>초기 비밀번호</th>
                </tr></thead>
                <tbody>
                    @foreach($createdUsers as $u)
                        <tr>
                            <td><code>{{ $u['login_id'] }}</code></td>
                            <td>{{ $u['name'] }}</td>
                            <td class="small">{{ $u['phone'] }}</td>
                            <td><span class="badge bg-light text-dark">{{ $u['role'] }}</span></td>
                            <td><code class="text-danger">{{ $u['password'] }}</code></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

@push('scripts')
<script>
function copyTable() {
    const rows = [['아이디','이름','휴대폰','역할','초기비밀번호']];
    document.querySelectorAll('#createdUsersTable tbody tr').forEach(tr => {
        const cells = [...tr.querySelectorAll('td')].map(td => td.innerText.trim());
        rows.push(cells);
    });
    const tsv = rows.map(r => r.join('\t')).join('\n');
    navigator.clipboard.writeText(tsv).then(() => alert('표가 복사되었습니다. 엑셀에 붙여넣기 가능합니다.'));
}
</script>
@endpush
@endsection
