{{-- ユーザーのプロフィール情報（基本情報・雇用・権限等）を表示するビュー --}}
@extends('layouts.app')

@section('content')
@php
$defaultPictures = config('user.default_profile_pictures');
$currentUrl = $user->profile_picture
? asset('image/' . $user->profile_picture)
: asset('image/' . $defaultPictures[$user->gender]);
$defaultUrl = asset('image/' . $defaultPictures[$user->gender]);
@endphp
<div class="row">
    <aside class="col-sm-4 mb-5">
        <div class="card bg-secondary">
            <div class="card-header">
                <h3 class="card-title text-light">{{ $user->name }}</h3>
            </div>
            <div class="card-body text-center">
                <img id="profileImg" class="rounded-circle img-fluid"
                    src="{{ $currentUrl }}"
                    alt="{{ $user->name }}のプロフィール画像">
                <div class="mt-3">
                    <button class="btn bg-white btn-block"
                        data-bs-toggle="modal"
                        data-bs-target="#photoEditModal">写真の編集</button>
                </div>
                @role('admin|super_admin')
                    @can('update', $user)
                        <form method="POST" action="{{ route('password.invite.send', $user) }}" class="mt-2">
                            @csrf
                            <button type="submit" class="btn btn-outline-light btn-block">
                                Send Invite Email
                            </button>
                        </form>
                    @endcan
                @endrole
            </div>
        </div>
    </aside>

    <!-- 写真編集モーダル -->
    <div class="modal fade" id="photoEditModal" tabindex="-1" aria-labelledby="photoEditModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title" id="photoEditModalLabel">プロフィール写真の編集</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                </div>

                <div class="modal-body text-center">
                    <img id="previewImg" src="{{ $currentUrl }}" alt="プレビュー"
                        class="rounded-circle img-fluid mb-3" style="max-width: 240px;">

                    <input type="file" id="photoInput" accept="image/*" class="d-none">
                    <p class="small text-muted mb-0">JPEG/PNG/WEBP/GIF（2MBまで）</p>
                </div>

                <div class="modal-footer justify-content-between">
                    <div class="d-flex gap-2">
                        <button type="button" id="btnSelect" class="btn btn-outline-secondary">選択</button>
                        <!-- 要件4: profile_picture が null でも、押せる（保存でNULL確定→デフォルト表示） -->
                        <button type="button" id="btnMarkDelete" class="btn btn-outline-danger">今の写真を削除</button>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">キャンセル</button>
                        <button type="button" id="btnSave" class="btn btn-primary" disabled>保存</button>
                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- JS（プレビュー、保存、削除） --}}
    <script>
        (() => {
            const profileImg = document.getElementById('profileImg');
            const previewImg = document.getElementById('previewImg');
            const photoInput = document.getElementById('photoInput');
            const btnSelect = document.getElementById('btnSelect');
            const btnMarkDelete = document.getElementById('btnMarkDelete');
            const btnSave = document.getElementById('btnSave');

            const CURRENT_URL = @json($currentUrl);
            const DEFAULT_URL = @json($defaultUrl);

            let selectedFile = null; // 仮アップロード用ファイル
            let pendingDelete = false; // 仮削除フラグ（保存でNULLにする）

            // 「選択」→ ファイルダイアログ
            btnSelect.addEventListener('click', () => photoInput.click());

            // ファイル選択 → その場で仮プレビュー
            photoInput.addEventListener('change', (e) => {
                const file = e.target.files?.[0] || null;
                if (!file) return;
                selectedFile = file;
                pendingDelete = false; // 新規選択したので削除フラグは下ろす

                const reader = new FileReader();
                reader.onload = (ev) => {
                    previewImg.src = ev.target.result;
                };
                reader.readAsDataURL(file);

                btnSave.disabled = false; // 保存可能
            });

            // 「今の写真を削除」→ 仮状態：デフォルト画像をプレビューし、保存でNULL確定
            btnMarkDelete.addEventListener('click', () => {
                selectedFile = null; // アップロードはしない
                pendingDelete = true; // 削除確定を希望
                previewImg.src = DEFAULT_URL; // 仮プレビューはデフォルト画像
                btnSave.disabled = false; // 保存可能
            });

            // 「保存」→ _method=PATCH でPOST送信（multipart/form-data）
            btnSave.addEventListener('click', async () => {
                const form = new FormData();
                form.append('_method', 'PATCH'); // メソッド偽装（POSTで送る）
                if (pendingDelete && !selectedFile) {
                    form.append('delete', '1');
                }
                if (selectedFile) {
                    form.append('photo', selectedFile);
                }

                btnSave.disabled = true;

                try {
                    const res = await fetch('{{ route("profile.photo.apply", $user) }}', {
                        method: 'POST', // ★ここはPOST
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: form
                    });

                    const data = await res.json().catch(() => null);
                    if (!res.ok) {
                        alert((data && data.message) || '保存に失敗しました。');
                        btnSave.disabled = false;
                        return;
                    }

                    // 反映：一覧側もプレビュー側も置き換え
                    profileImg.src = data.url;
                    previewImg.src = data.url;

                    // 仮状態をクリア
                    selectedFile = null;
                    pendingDelete = false;
                    btnSave.disabled = true;

                    // モーダルを閉じる
                    const modalEl = document.getElementById('photoEditModal');
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    modal.hide();

                } catch (e) {
                    alert('ネットワークエラーが発生しました。');
                    btnSave.disabled = false;
                }
            });

            // キャンセル/閉じる → 仮状態を破棄
            document.getElementById('photoEditModal').addEventListener('hidden.bs.modal', () => {
                selectedFile = null;
                pendingDelete = false;
                btnSave.disabled = true;
                previewImg.src = profileImg.src; // 画面実体に戻す
                photoInput.value = ''; // クリア
            });
        })();
    </script>

    <div class="col-sm-8">
        <ul class="nav nav-tabs nav-justified mb-3">
            <li class="nav-item">
                <a href="#" class="nav-link {{ Request::is('profile') || Request::is('profile/*') ? 'active' : '' }}">
                    プロファイル
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('calendar.index') }}" class="nav-link {{ Request::is('calender') || Request::is('calender/*') ? 'active' : '' }}">
                    スケジュール
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('leave.apply.create') }}" class="nav-link {{ Request::is('leaveApply') || Request::is('leaveApply/*') ? 'active' : '' }}">
                    有給申請
                </a>
            </li>
        </ul>
        @include('user.profile.personal',   ['user' => $user])
        @include('user.profile.basic',      ['user' => $user])
        @include('user.profile.employment', ['user' => $user])
        @include('user.profile.role',       ['user' => $user])
    </div>
</div>

@role('admin|super_admin')
    @php $updateFieldUrl = route('admin.user.updateField', ['user' => $user]); @endphp
@else
    @php $updateFieldUrl = route('user.updateField'); @endphp
@endrole

<script>
    const updateFieldUrl = @json($updateFieldUrl);
</script>

<script>
    function editField(field) {
        document.getElementById(`${field}_display`).classList.add('d-none');
        document.getElementById(`${field}_input`).classList.remove('d-none');
        document.getElementById(`${field}_edit`).classList.add('d-none');
        document.getElementById(`${field}_save`).classList.remove('d-none');
        document.getElementById(`${field}_cancel`).classList.remove('d-none');

        // 編集前の値を保持（キャンセル時に使う）
        const input = document.getElementById(`${field}_input`);
        input.setAttribute('data-original', input.value);
    }

    function saveField(field) {
        const value = document.getElementById(`${field}_input`).value;

        const confirmFields = ['employee_code', 'name', 'gender'];
        if (confirmFields.includes(field)) {
            if (!confirm("重要な情報です。本当に更新しますか？")) {
                return; // キャンセルしたら終了
            }
        }

        fetch(updateFieldUrl, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    field,
                    value
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.message) {
                    document.getElementById(`${field}_display`).textContent = value;
                    document.getElementById(`${field}_display`).classList.remove('d-none');
                    document.getElementById(`${field}_input`).classList.add('d-none');
                    document.getElementById(`${field}_edit`).classList.remove('d-none');
                    document.getElementById(`${field}_save`).classList.add('d-none');
                    document.getElementById(`${field}_cancel`).classList.add('d-none');
                } else {
                    alert(data.error || '更新に失敗しました');
                }
            });
    }

    function cancelField(field) {
        const display = document.getElementById(`${field}_display`);
        const input = document.getElementById(`${field}_input`);
        const editBtn = document.getElementById(`${field}_edit`);
        const saveBtn = document.getElementById(`${field}_save`);
        const cancelBtn = document.getElementById(`${field}_cancel`);

        // 入力値を元に戻す
        const originalValue = input.getAttribute('data-original');
        input.value = originalValue;

        // 表示切り替え
        display.classList.remove('d-none');
        input.classList.add('d-none');
        saveBtn.classList.add('d-none');
        cancelBtn.classList.add('d-none');
        editBtn.classList.remove('d-none');
    }
</script>

@endsection
