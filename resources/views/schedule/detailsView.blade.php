            {{-- ▼▼▼ 閲覧専用：Schedule Details（高密度） ▼▼▼ --}}
            <div class="schedule-details-compact">

                @php $allSegments = $seriesByLine[$line->id] ?? []; @endphp

                @if(empty($allSegments))
                <div class="text-muted" style="font-size:0.78rem; padding: 0.1rem 0;">No details</div>
                @else

                @foreach($allSegments as $seg)
                @php
                $segStart = $seg['start']?->toDateString();
                $segEnd   = $seg['end']?->toDateString();
                $items    = $seg['items'];
                @endphp

                {{-- 期間ヘッダー --}}
                <div class="schedule-detail-period-header">
                    <span>{{ $segStart }} – {{ $segEnd ?? 'Open' }}</span>
                    <span class="sdr-count">{{ count($items) }} items</span>
                </div>

                {{-- 期間内の各 item を1行で表示 --}}
                @foreach($items as $it)
                <div class="schedule-detail-row">
                    {{-- 1列目：lesson_code（lesson_minute） ※code無ければnameでフォールバック --}}
                    <span class="text-truncate">
                        {{ $it['code'] ?? $it['name'] ?? '—' }}@if(!empty($it['minute']))<span class="text-muted"> ({{ $it['minute'] }}m)</span>@endif
                    </span>
                    {{-- 2列目：start_time ~ end_time（自動計算結果） --}}
                    <span class="text-monospace">{{ $it['start'] ?? '--:--' }} - {{ $it['end'] ?? '--:--' }}</span>
                </div>
                @endforeach

                @endforeach

                @endif

            </div>
            {{-- ▲▲▲ 詳細ここまで ▲▲▲ --}}
