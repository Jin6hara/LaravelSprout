// Bootstrap 5 Toast helper (global)

function ensureToastDom() {
    // 既にあるなら何もしない
    if (document.getElementById('appToast')) return;

    const wrapper = document.createElement('div');
    wrapper.innerHTML = `
    <div class="toast-container position-fixed bottom-0 start-50 translate-middle-x p-3" style="z-index:1080;">
      <div id="appToast"
           class="toast align-items-center text-white bg-dark border-0"
           role="alert" aria-live="assertive" aria-atomic="true"
           data-bs-delay="9000">
        <div class="d-flex">
          <div class="toast-body" id="appToastBody">Saved.</div>
          <button type="button"
                  class="btn-close btn-close-white me-2 m-auto"
                  data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
      </div>
    </div>
  `.trim();

    // body の最後に追加
    document.body.appendChild(wrapper.firstElementChild);
}

window.showToast = function (message, { variant = 'success', delay = 9000 } = {}) {
    // Bootstrap bundle がない/まだ読み込み前なら安全に抜ける
    if (typeof window.bootstrap === 'undefined' || !window.bootstrap.Toast) return;

    ensureToastDom();

    const toastEl = document.getElementById('appToast');
    const bodyEl = document.getElementById('appToastBody');
    if (!toastEl || !bodyEl) return;

    // 色切替
    toastEl.classList.remove('bg-dark', 'bg-success', 'bg-danger', 'bg-primary', 'bg-warning', 'bg-info');
    toastEl.classList.add('bg-' + variant);

    // メッセージ
    bodyEl.textContent = message ?? '';

    // 遅延
    toastEl.setAttribute('data-bs-delay', String(delay));

    // 表示
    let toast = window.bootstrap.Toast.getInstance(toastEl);
    if (!toast) toast = new window.bootstrap.Toast(toastEl);
    toast.show();
};

// 任意：DOMができたら土台だけ先に作っておく（なくてもOK）
document.addEventListener('DOMContentLoaded', () => {
    // bootstrap が居るときだけ作る（不要なら消してOK）
    if (typeof window.bootstrap !== 'undefined' && window.bootstrap.Toast) {
        ensureToastDom();
    }
});

document.addEventListener('DOMContentLoaded', () => {
    const el = document.getElementById('toastFlash');
    if (!el) return;

    const toast = el.dataset.toast ? JSON.parse(el.dataset.toast) : null;
    const errors = el.dataset.errors ? JSON.parse(el.dataset.errors) : null;

    if (toast) window.showToast(toast, { variant: 'success', delay: 9000 });
    if (Array.isArray(errors)) {
        errors.forEach(err => window.showToast(err, { variant: 'danger', delay: 9000 }));
    }
});