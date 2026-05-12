@if($users->count())
<div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="mb-0">ユーザー一覧</h5>
    <small class="text-muted">{{ $users->total() }} 件</small>
</div>

<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-2">
    @foreach($users as $user)

    {{-- 1行1～4件：col-12 col-sm-6 col-md-4 col-lg-3で自動調整 --}}
    <div class="col-12 col-sm-6 col-md-4 col-lg-3">
        <div class="card h-100 shadow-sm">
            <div class="card-body p-2">
                {{-- アバター（左）＋ 名前・性別（右） --}}
                <div class="d-flex align-items-center gap-2 mb-2">
                    <img
                        src="{{ $user->profile_image_url }}"
                        alt="profile picture of {{ $user->name }}"
                        class="rounded-circle img-thumbnail flex-shrink-0"
                        style="width: 100px; height: 100px; object-fit: cover;">
                    <div class="min-w-0">
                        <h6 class="mb-0 lh-sm">
                            <a href="{{ route('admin.user.profile', $user) }}" class="text-break">
                                {{ $user->name }}
                            </a>
                        </h6>
                        <div class="text-muted small fw-semibold my-1">{{ $user->employee_code }}</div>
                        <span class="badge bg-secondary">{{ ucfirst($user->gender) }}</span>
                    </div>
                </div>

                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between px-0 py-1">
                        <span class="text-muted">Email</span>
                        <a class="text-decoration-none" href="mailto:{{ $user->email }}">{{ $user->email }}</a>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0 py-1">
                        <span class="text-muted">Phone</span>
                        <span>{{ $user->phone_number ?? '—' }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0 py-1">
                        <span class="text-muted">District</span>
                        <span>{{ $user->district?->name ?? '—' }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0 py-1">
                        <span class="text-muted">Department</span>
                        <span>{{ $user->department?->name ?? '—' }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0 py-1">
                        <span class="text-muted">Contract Type</span>
                        <span>{{ $user->latestEmploymentTerm?->type_name ?? '—' }} ({{ $user->latestEmploymentTerm?->type_code ?? '—' }})</span>
                    </li>
                </ul>
            </div>

            <div class="card-footer bg-white py-1">
                <small class="text-muted">
                    Updated: {{ $user->updated_at?->format('Y-m-d H:i') }}
                </small>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="mt-3">
    {{-- ページング（検索条件を維持） --}}
    {{ $users->withQueryString()->links() }}
</div>
@else
<div class="alert alert-light border">
    データがありません。
</div>
@endif