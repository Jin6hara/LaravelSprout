@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Create Message (Admin)</h2>
</div>

<form id="postForm" method="POST" action="{{ route('posts.send') }}" enctype="multipart/form-data">
    @csrf

    <div class="card mb-3">
        <div class="card-body">

            {{-- タイプ --}}
            <div class="mb-3">
                @php use App\Enums\PostType; @endphp
                <label class="form-label">Type <span class="text-danger">*</span></label>
                <select name="type" class="form-select" required id="postTypeSelect">
                    <option value="announcement" data-default-reply="{{ PostType::Announcement->defaultAllowReplies()}}">Notification</option>
                    <option value="inquiry" data-default-reply="{{ PostType::Inquiry->defaultAllowReplies() ? 1 : 0 }}">Inquiry</option>
                    <option value="dm" data-default-reply="{{ PostType::DirectMessage->defaultAllowReplies() ? 1 : 0 }}">Direct Message</option>
                    <option value="notice_urgent" data-default-reply="{{ PostType::NoticeUrgent->defaultAllowReplies() ? 1 : 0 }}">Urgent</option>
                    <option value="maintenance" data-default-reply="{{ PostType::Maintenance->defaultAllowReplies() ? 1 : 0 }}">Maintenance</option>
                    <option value="other" data-default-reply="{{ PostType::Other->defaultAllowReplies() ? 1 : 0 }}">Other</option>
                </select>
                <div class="form-text">type decides default values such as allow replies.</div>
            </div>

            {{-- タイトル --}}
            <div class="mb-3">
                <label class="form-label">Title</label>
                <input type="text" name="title" value="{{ old('title') }}" class="form-control" maxlength="255">
            </div>

            {{-- 添付 --}}
            <div class="mb-3">
                <label class="form-label">Attachment(s)</label>
                <input type="file" name="attachments[]" class="form-control" multiple
                    accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip">
                <div class="form-text">Up to 10 files, max 10MB each.</div>
            </div>

            {{-- 本文 --}}
            <div class="mb-3">
                <label class="form-label">Body <span class="text-danger">*</span></label>
                <textarea name="body" class="form-control" rows="8" required>{{ old('body') }}</textarea>
            </div>

            {{-- 送信先（選択モーダル＋チップ表示） --}}
            <div class="mb-2">
                <label class="form-label">Recepients<span class="text-danger">*</span></label>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#recipientModal">
                        Select
                    </button>
                    <span class="text-muted small align-self-center">Search → Check → Add</span>
                </div>

                <div id="recipientChips" class="mt-2 d-flex flex-wrap gap-2">
                    {{-- 選択済みユーザーのチップがJSでここに入ります --}}
                    @if(is_array(old('recipients')))
                    @foreach(old('recipients', []) as $uid)
                    <input type="hidden" name="recipients[]" value="{{ $uid }}">
                    @endforeach
                    @endif
                </div>
                <div class="form-text">Choose at least one recipient.</div>
            </div>

            {{-- 期限 --}}
            <div class="mb-3">
                <label class="form-label">Expires At</label>
                <input type="datetime-local" name="expires_at"
                    value="{{ old('expires_at') }}"
                    class="form-control">
                <div class="form-text">Leave blank for no expiration. After the expiration date the message will be hidden from recipients (the sender can still view it).</div>
            </div>

            {{-- 返信可否 --}}
            <div class="mb-3 form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch"
                    id="switchAllowReplies" name="allow_replies"
                    value="1" {{ old('allow_replies', 1) ? 'checked' : '' }}>
                <label class="form-check-label" for="switchAllowReplies">Allow Replies</label>
                <input type="hidden" name="allow_replies" value="{{ old('allow_replies', 1) ? 1 : 0 }}">
            </div>

            <div class="d-flex justify-content-end mt-3">
                <button type="submit" class="btn btn-primary">Send</button>
            </div>

        </div>
    </div>
</form>

{{-- 宛先モーダル --}}
<div class="modal fade" id="recipientModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Choose Recipient(s)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="input-group mb-2">
                    <input type="text" id="userSearchInput" class="form-control" placeholder="Name, Employee Code, Email">
                    <button class="btn btn-outline-secondary" type="button" id="userSearchBtn">Search</button>
                </div>

                <div class="table-responsive border rounded">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40px;"></th>
                                <th>Name</th>
                                <th>Employee Code</th>
                                <th>Email</th>
                            </tr>
                        </thead>
                        <tbody id="userResultBody">
                            <tr>
                                <td colspan="4" class="text-muted text-center py-3">Search</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="modal-footer">
                <span class="me-auto small text-muted" id="modalHint">Selecting 0</span>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="addRecipientsBtn">Add</button>
            </div>
        </div>
    </div>
</div>

{{-- 最小JS（jQuery不要） --}}
<script>
    (() => {
        const resultBody = document.getElementById('userResultBody');
        const searchBtn = document.getElementById('userSearchBtn');
        const searchInput = document.getElementById('userSearchInput');
        const addBtn = document.getElementById('addRecipientsBtn');
        const chipsWrap = document.getElementById('recipientChips');
        const modalHint = document.getElementById('modalHint');

        // 既に選ばれているID集合
        const selected = new Set(Array.from(document.querySelectorAll('input[name="recipients[]"]')).map(i => i.value));

        function renderChip(user) {
            const chip = document.createElement('span');
            chip.className = 'badge rounded-pill text-bg-primary d-inline-flex align-items-center gap-1 p-2';
            chip.dataset.userId = user.id;
            chip.innerHTML = `
      <span>${user.family_name ?? ''} ${user.first_name ?? ''} <span class="opacity-75">[${user.employee_code ?? ''}]</span></span>
      <button type="button" class="btn-close btn-close-white btn-sm ms-1" aria-label="Remove"></button>
    `;
            chip.querySelector('button').addEventListener('click', () => {
                selected.delete(String(user.id));
                // hidden input 削除
                const hidden = chipsWrap.querySelector('input[type="hidden"][value="' + user.id + '"]');
                hidden?.remove();
                chip.remove();
            });
            return chip;
        }

        // 検索
        async function doSearch() {
            resultBody.innerHTML = `<tr><td colspan="4" class="text-muted text-center py-3">検索中...</td></tr>`;
            const q = searchInput.value.trim();
            const url = new URL(`{{ route('api.users.search') }}`);
            if (q) url.searchParams.set('q', q);

            const res = await fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const users = await res.json();

            if (!users.length) {
                resultBody.innerHTML = `<tr><td colspan="4" class="text-muted text-center py-3">該当なし</td></tr>`;
                modalHint.textContent = '0件選択中';
                return;
            }

            resultBody.innerHTML = users.map(u => {
                const checked = selected.has(String(u.id)) ? 'checked' : '';
                return `
        <tr>
          <td><input class="form-check-input user-check" type="checkbox" value="${u.id}" ${checked}></td>
          <td>${(u.family_name ?? '')} ${(u.first_name ?? '')}</td>
          <td>${u.employee_code ?? ''}</td>
          <td>${u.email ?? ''}</td>
        </tr>
      `;
            }).join('');

            updateCount();
        }

        function updateCount() {
            const checks = resultBody.querySelectorAll('.user-check:checked');
            modalHint.textContent = `${checks.length}件選択中`;
        }

        searchBtn.addEventListener('click', doSearch);
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                doSearch();
            }
        });
        resultBody.addEventListener('change', (e) => {
            if (e.target.classList.contains('user-check')) updateCount();
        });

        // 追加
        addBtn.addEventListener('click', () => {
            const checks = resultBody.querySelectorAll('.user-check:checked');
            checks.forEach(chk => {
                const id = String(chk.value);
                if (!selected.has(id)) {
                    selected.add(id);
                    // hidden input
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'recipients[]';
                    input.value = id;
                    chipsWrap.appendChild(input);
                    // chip 表示（行から氏名等を拾うため最小限情報は検索結果の dataset に持たせてもOK）
                    const tr = chk.closest('tr');
                    const user = {
                        id,
                        family_name: tr.children[1].textContent.split(' ')[0] ?? '',
                        first_name: tr.children[1].textContent.split(' ')[1] ?? '',
                        employee_code: tr.children[2].textContent ?? ''
                    };
                    chipsWrap.appendChild(renderChip(user));
                }
            });

            // 閉じる
            const m = bootstrap.Modal.getInstance(document.getElementById('recipientModal'));
            m?.hide();
        });

        // 初期 old() 分のチップは必要に応じてサーバ側で描画してもOK（簡略のため省略）
    })();

    // switch が外れた時にも 0 が送られるように hidden を連動
    (function() {
        const sw = document.getElementById('switchAllowReplies');
        const hidden = document.querySelector('input[type="hidden"][name="allow_replies"]');
        const sync = () => hidden.value = sw.checked ? 1 : 0;
        sw.addEventListener('change', sync);
        sync();
    })();

    // UI側でも既定を切替（任意）
    (function() {
        const typeSel = document.getElementById('postTypeSelect');
        const replySw = document.getElementById('switchAllowReplies');
        const hidden = document.querySelector('input[type="hidden"][name="allow_replies"]');

        function applyDefault() {
            const opt = typeSel.options[typeSel.selectedIndex];
            const allow = opt.dataset.defaultReply === '1';

            if (replySw) replySw.checked = allow;
            if (hidden) hidden.value = allow ? 1 : 0;
        }
        typeSel?.addEventListener('change', applyDefault);
        // 初期
        if (!'{{ old('
            allow_replies ') }}') applyDefault();
    })();
</script>
@endsection