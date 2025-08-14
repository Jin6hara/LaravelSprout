@if($users->count())
<div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="mb-0">ユーザー一覧</h5>
    <small class="text-muted">{{ $users->total() }} 件</small>
</div>

<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
    @foreach($users as $user)

    {{-- 1行1～4件：col-12 col-sm-6 col-md-4 col-lg-3で自動調整 --}}
    <div class="col-12 col-sm-6 col-md-4 col-lg-3">
        <div class="card h-100 shadow-sm">
            {{-- 丸い小さめアバター --}}
            <div class="pt-3 text-center">
                <img
                    src="{{ $user->profile_image_url }}"
                    alt="profile picture of {{ $user->name }}"
                    class="rounded-circle img-thumbnail"
                    style="width: 128px; height: 128px; object-fit: cover;">
            </div>

            <div class="card-body">
                <h6 class="card-title mb-1 text-center">
                    <a href="{{ route('admin.user.profile', $user) }}">
                    {{ $user->name }}
                    </a>
                </h6>
                <p class="text-center mb-2">
                    <span class="badge bg-secondary">{{ ucfirst($user->gender) }}</span>
                </p>

                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Code</span>
                        <span class="fw-semibold">{{ $user->employee_code }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Email</span>
                        <a class="text-decoration-none" href="mailto:{{ $user->email }}">{{ $user->email }}</a>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Phone</span>
                        <span>{{ $user->phone_number ?? '—' }}</span>
                    </li>
                </ul>
            </div>

            <div class="card-footer bg-white">
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