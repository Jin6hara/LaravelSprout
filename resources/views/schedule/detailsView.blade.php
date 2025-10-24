            {{-- ▼▼▼ 閲覧専用：Schedule Details（高密度） ▼▼▼ --}}
            <div class="card-body py-1">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <div class="fw-semibold small">Details (Time-series)</div>
                    <div class="small text-muted">
                        Segments: {{ count($seriesByLine[$line->id] ?? []) }}
                    </div>
                </div>

                @php $segments = $seriesByLine[$line->id] ?? []; @endphp

                @if(empty($segments))
                <div class="text-muted small">（詳細は登録されていません）</div>
                @else
                <div class="d-flex flex-column gap-2">
                    @foreach($segments as $seg)
                    @php
                    $segStart = $seg['start']?->toDateString();
                    $segEnd = $seg['end']?->toDateString();
                    $period = $segStart . ' 〜 ' . ($segEnd ?? 'Open');
                    $items = $seg['items'];
                    @endphp

                    <div class="border rounded-3 p-1">
                        <div class="d-flex justify-content-between align-items-center mb-0">
                            <div class="small fw-semibold">
                                {{ $period }}
                            </div>
                            <div class="small text-muted">Total: {{ count($items) }}</div>
                        </div>

                        {{-- 横方向に高密度で並べる（各アイテムは2行表示） --}}
                        <div class="d-flex flex-wrap gap-1">
                            @foreach($items as $it)
                            <div class="border rounded-3 px-2 py-1 bg-light small"
                                style="min-width: 130px; max-width: 200px; line-height: 1.2;">
                                {{-- 1行目：lesson_code（lesson_minute） ※code無ければnameでフォールバック --}}
                                <div class="fw-semibold text-truncate">
                                    {{ $it['code'] ?? $it['name'] ?? '—' }}
                                    @if(!empty($it['minute']))
                                    <span class="text-muted">({{ $it['minute'] }}m)</span>
                                    @endif
                                </div>
                                {{-- 2行目：start_time ~ end_time（自動計算結果） --}}
                                <div class="text-monospace">
                                    {{ $it['start'] ?? '--:--' }} ~ {{ $it['end'] ?? '--:--' }}
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
            {{-- ▲▲▲ 詳細ここまで ▲▲▲ --}}