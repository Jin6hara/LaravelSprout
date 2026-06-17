// ユーザー名・社員番号のインクリメンタル検索とオートコンプリート入力を制御するスクリプト
(function () {
  function labelFor(user) {
    const name = [user.first_name, user.family_name].filter(Boolean).join(' ').trim()
      || user.name
      || user.email
      || `User #${user.id}`;
    return user.employee_code ? `${name} [${user.employee_code}]` : name;
  }

  function debounce(fn, wait = 180) {
    let timer = null;
    return (...args) => {
      clearTimeout(timer);
      timer = setTimeout(() => fn(...args), wait);
    };
  }

  function init(input) {
    if (!input || input.dataset.userAutocompleteReady === '1') return;
    input.dataset.userAutocompleteReady = '1';

    const url = input.dataset.userAutocompleteUrl;
    const hiddenSelector = input.dataset.userAutocompleteHidden || '';
    if (!hiddenSelector) return;

    const hidden = document.querySelector(hiddenSelector);
    const listId = input.getAttribute('list') || input.dataset.userAutocompleteList;
    const list = listId ? document.getElementById(listId) : null;
    if (!url || !hidden || !list) return;

    const selectedByLabel = new Map();

    function clearSelection() {
      hidden.value = '';
    }

    function syncSelection() {
      const selected = selectedByLabel.get(input.value.trim());
      hidden.value = selected ? selected.id : '';
      input.setCustomValidity('');
    }

    async function search() {
      const query = input.value.trim();
      const endpoint = new URL(url, window.location.origin);
      endpoint.searchParams.set('q', query);
      endpoint.searchParams.set('limit', input.dataset.userAutocompleteLimit || '20');

      const res = await fetch(endpoint.toString(), { headers: { Accept: 'application/json' } });
      if (!res.ok) return;

      const users = await res.json();
      selectedByLabel.clear();
      list.innerHTML = '';

      users.forEach((user) => {
        const label = labelFor(user);
        selectedByLabel.set(label, user);

        const option = document.createElement('option');
        option.value = label;
        list.appendChild(option);
      });

      syncSelection();
    }

    const debouncedSearch = debounce(search);

    input.addEventListener('focus', search);
    input.addEventListener('input', () => {
      clearSelection();
      debouncedSearch();
    });
    input.addEventListener('change', syncSelection);

    input.form?.addEventListener('submit', (event) => {
      syncSelection();
      input.setCustomValidity('');
    });
  }

  function initAll(root = document) {
    root.querySelectorAll('[data-user-autocomplete]').forEach(init);
  }

  window.UserAutocomplete = { init, initAll };
  document.addEventListener('DOMContentLoaded', () => initAll());
})();
