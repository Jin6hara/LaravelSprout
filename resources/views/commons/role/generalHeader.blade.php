@role('general')
<style>
    .header-btn {
        /* btn-sm の雰囲気を維持しつつ高さを統一 */
        display: inline-flex;
        color: #cfcfcfff;
        align-items: center;
        justify-content: center;
        height: 36px;
        /* ← 統一高さ（必要なら 32〜40px で微調整OK） */
        padding: 1px 5px !important;
        /* ←ここを修正 */
        /* ← 横パディングを 1px に固定 */
        line-height: 1.1;
        /* 複数行の詰め調整 */
        white-space: normal;
        /* 改行を有効化 */
        text-align: center;
        margin: 0 1px;
    }

    /* li の間隔を少しだけ詰める（任意） */
    .navbar .nav-item {
        margin-right: .25rem;
    }

    .navbar .nav-item:last-child {
        margin-right: 0;
    }
</style>

<li class="nav-item">
    <a class="btn btn-sm btn-outline-secondary header-btn" href="{{ route('calendar.index') }}">
        Schedule
    </a>
</li>

<span class="nav-divider">|</span>

<li class="nav-item">
    <a class="btn btn-sm btn-outline-secondary header-btn" href="{{ route('expenses.edit') }}">
        CER
    </a>
</li>

<span class="nav-divider">|</span>

<li class="nav-item">
    <a class="btn btn-sm btn-outline-secondary header-btn" href="{{ route('schools.search') }}">
        School<br>Map
    </a>
</li>

<span class="nav-divider">|</span>

<li class="nav-item">
    <a class="btn btn-sm btn-outline-secondary header-btn" href="{{ route('absence.edit', ['user' => auth()->user()->employee_code]) }}">
        Absence
    </a>
</li>

<span class="nav-divider">|</span>

<li class="nav-item">
    <a class="btn btn-sm btn-outline-secondary header-btn" href="{{ route('leave.apply.create') }}">
        ALP
    </a>
</li>

<span class="nav-divider">|</span>

<li class="nav-item">
    <a class="btn btn-sm btn-outline-secondary header-btn" href="{{ route('user.profile') }}">
        Profile
    </a>
</li>

<span class="nav-divider">|</span>
@endrole