<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold text-gray-100">Sites</h1>
        <button wire:click="check" class="text-xs text-gray-500 hover:text-gray-300 transition-colors">
            Check all
        </button>
    </div>

    @if(empty($sites))
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-8 text-center">
            <p class="text-gray-500 text-sm">No sites discovered. Make sure Nginx is running and sites are enabled.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach($sites as $site)
                @php
                    $sslWarning = isset($site['ssl_days']) && $site['ssl_days'] !== null && $site['ssl_days'] < 14;
                    $sslCaution = isset($site['ssl_days']) && $site['ssl_days'] !== null && $site['ssl_days'] < 30;
                    $borderClass = $sslWarning ? 'border-red-900/60' : ($sslCaution ? 'border-amber-900/50' : 'border-gray-800');
                    $msColor = match(true) {
                        ($site['response_ms'] ?? null) === null => 'text-gray-500',
                        $site['response_ms'] < 200 => 'text-green-400',
                        $site['response_ms'] < 500 => 'text-amber-400',
                        default => 'text-red-400',
                    };
                @endphp
                <div class="bg-gray-900 border {{ $borderClass }} rounded-lg overflow-hidden">

                    {{-- Site row (clickable to expand) --}}
                    <div wire:click="selectSite('{{ $site['url'] }}')"
                         class="w-full px-4 sm:px-5 py-4 flex items-center justify-between gap-4 hover:bg-gray-800/30 transition-colors cursor-pointer">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="w-2 h-2 rounded-full flex-shrink-0 {{ $site['status'] === 'up' ? 'bg-green-400' : 'bg-red-500' }}"></span>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <p class="text-sm font-medium text-gray-100">{{ $site['name'] }}</p>
                                    @if(isset($site['uptime_24h']) && $site['uptime_24h'] < 100)
                                        <span class="text-xs font-mono px-1.5 py-0.5 rounded bg-amber-900/30 text-amber-400">{{ $site['uptime_24h'] }}%</span>
                                    @elseif(isset($site['uptime_24h']))
                                        <span class="text-xs font-mono text-gray-600">{{ $site['uptime_24h'] }}%</span>
                                    @endif
                                    @if(isset($site['ssl_days']))
                                        @if($site['ssl_days'] === null)
                                            <span class="text-xs font-mono text-gray-600">no SSL</span>
                                        @else
                                            <span class="text-xs font-mono px-1.5 py-0.5 rounded
                                                {{ $sslWarning ? 'text-red-400 bg-red-950/50' : ($sslCaution ? 'text-amber-400 bg-amber-950/50' : 'text-green-400 bg-green-950/50') }}">
                                                SSL {{ $site['ssl_days'] }}d
                                            </span>
                                        @endif
                                    @endif
                                </div>
                                <div class="flex items-center gap-3 mt-1">
                                    <p class="text-xs text-gray-500 font-mono truncate">{{ $site['url'] }}</p>
                                    {{-- Mini status bar --}}
                                    @if(!empty($site['recent_statuses']))
                                        <div class="flex gap-px flex-shrink-0">
                                            @foreach($site['recent_statuses'] as $s)
                                                <div class="w-1.5 h-3.5 rounded-sm {{ $s === 'up' ? 'bg-green-500/60' : 'bg-red-500' }}"></div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-4 sm:gap-6 flex-shrink-0">
                            <div class="text-right">
                                <p class="text-xs text-gray-500">Status</p>
                                <p class="text-sm font-medium {{ $site['status'] === 'up' ? 'text-green-400' : 'text-red-400' }}">
                                    {{ strtoupper($site['status']) }}
                                    @if($site['status_code'])
                                        <span class="text-gray-500 font-normal ml-1 hidden sm:inline">({{ $site['status_code'] }})</span>
                                    @endif
                                </p>
                            </div>

                            <div class="text-right hidden sm:block">
                                <p class="text-xs text-gray-500">Response</p>
                                <p class="text-sm font-mono {{ $msColor }}">
                                    {{ $site['response_ms'] ? $site['response_ms'].'ms' : '—' }}
                                </p>
                            </div>

                            <button wire:click.stop="checkNow('{{ $site['url'] }}')"
                                    class="text-xs text-gray-600 hover:text-gray-300 transition-colors font-mono">
                                check
                            </button>

                            <svg class="w-3.5 h-3.5 text-gray-600 transition-transform duration-200 {{ $selectedSite === $site['url'] ? 'rotate-90' : '' }}"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </div>
                    </div>

                    {{-- Detail panel --}}
                    @if($selectedSite === $site['url'])
                        <div class="border-t border-gray-800 p-4 sm:p-5" wire:loading.class="opacity-50">

                            {{-- 24h stats row --}}
                            @if(isset($site['avg_ms']) || isset($site['check_count']))
                                <div class="flex flex-wrap gap-6 mb-5">
                                    @if(isset($site['avg_ms']) && $site['avg_ms'] !== null)
                                        <div>
                                            <p class="text-xs text-gray-600 mb-0.5">Avg (24h)</p>
                                            <p class="text-sm font-mono text-gray-300">{{ $site['avg_ms'] }}ms</p>
                                        </div>
                                    @endif
                                    @if(isset($site['p95_ms']) && $site['p95_ms'] !== null)
                                        <div>
                                            <p class="text-xs text-gray-600 mb-0.5">p95 (24h)</p>
                                            <p class="text-sm font-mono {{ $site['p95_ms'] >= 500 ? 'text-red-400' : ($site['p95_ms'] >= 200 ? 'text-amber-400' : 'text-gray-300') }}">{{ $site['p95_ms'] }}ms</p>
                                        </div>
                                    @endif
                                    @if(isset($site['max_ms']) && $site['max_ms'] !== null)
                                        <div>
                                            <p class="text-xs text-gray-600 mb-0.5">Slowest (24h)</p>
                                            <p class="text-sm font-mono {{ $site['max_ms'] >= 500 ? 'text-red-400' : ($site['max_ms'] >= 200 ? 'text-amber-400' : 'text-gray-300') }}">{{ $site['max_ms'] }}ms</p>
                                        </div>
                                    @endif
                                    @if(!empty($site['last_down_at']))
                                        <div>
                                            <p class="text-xs text-gray-600 mb-0.5">Last down</p>
                                            <p class="text-sm font-mono text-amber-400">{{ $site['last_down_at'] }}</p>
                                        </div>
                                    @endif
                                    @if(isset($site['check_count']) && $site['check_count'] > 0)
                                        <div>
                                            <p class="text-xs text-gray-600 mb-0.5">Total checks</p>
                                            <p class="text-sm font-mono text-gray-500">{{ number_format($site['check_count']) }}</p>
                                        </div>
                                    @endif
                                </div>
                            @endif

                            {{-- Security checks --}}
                            @if(isset($site['security_headers']) || isset($site['redirects_to_https']))
                                <div class="mb-5">
                                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">Security</p>
                                    <div class="flex flex-wrap gap-2">
                                        @if(isset($site['redirects_to_https']))
                                            <span class="text-xs font-mono px-1.5 py-0.5 rounded {{ $site['redirects_to_https'] ? 'bg-green-950/50 text-green-400' : 'bg-amber-950/50 text-amber-400' }}">
                                                HTTP→HTTPS
                                            </span>
                                        @endif
                                        @if(isset($site['security_headers']) && $site['security_headers'] !== null)
                                            @foreach(['hsts' => 'HSTS', 'xcto' => 'X-Content-Type', 'xfo' => 'X-Frame-Options', 'csp' => 'CSP'] as $key => $label)
                                                <span class="text-xs font-mono px-1.5 py-0.5 rounded {{ $site['security_headers'][$key] ? 'bg-green-950/50 text-green-400' : 'bg-gray-800 text-gray-600' }}">
                                                    {{ $label }}
                                                </span>
                                            @endforeach
                                        @endif
                                    </div>
                                </div>
                            @endif

                            {{-- Downtime incidents --}}
                            @if(!empty($siteIncidents))
                                <div class="mb-5">
                                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">Downtime Incidents</p>
                                    <div class="space-y-1">
                                        @foreach($siteIncidents as $incident)
                                            <div class="flex items-center gap-3 text-xs font-mono">
                                                <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $incident['resolved'] ? 'bg-gray-600' : 'bg-red-500' }}"></span>
                                                <span class="text-gray-400">{{ $incident['started_at'] }}</span>
                                                <span class="text-gray-600">{{ $incident['duration_min'] }}m</span>
                                                @if(! $incident['resolved'])
                                                    <span class="text-red-400">ongoing</span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Response time chart --}}
                            <div class="flex items-center gap-3 mb-3">
                                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Response Time History</p>
                                @if(isset($site['uptime_24h']))
                                    <span class="text-xs font-mono {{ $site['uptime_24h'] < 100 ? 'text-amber-400' : 'text-green-400' }}">{{ $site['uptime_24h'] }}% uptime</span>
                                @endif
                            </div>

                            @if(empty($siteHistory))
                                <p class="text-sm text-gray-600 font-mono mb-5">No history yet — checks are recorded every 5 minutes.</p>
                            @else
                                <div id="siteHistoryData" data-history="{{ json_encode($siteHistory) }}"></div>
                                <div class="h-32 mb-5" wire:ignore>
                                    <canvas id="siteHistoryChart"></canvas>
                                </div>
                            @endif

                            {{-- Nginx config viewer --}}
                            @if($nginxConfig)
                                <div x-data="{ open: false }"
                                     x-effect="if (open) $nextTick(() => { if ($refs.configCode && !$refs.configCode.dataset.highlighted) hljs.highlightElement($refs.configCode) })">
                                    <button @click="open = !open"
                                            class="flex items-center gap-2 text-xs text-gray-500 hover:text-gray-300 transition-colors font-mono">
                                        <svg class="w-3 h-3 transition-transform" :class="{ 'rotate-90': open }"
                                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                        nginx config
                                    </button>
                                    <div x-show="open" x-cloak class="mt-3" wire:ignore>
                                        <pre class="text-xs rounded-lg overflow-x-auto max-h-72 overflow-y-auto leading-relaxed !bg-gray-950 !p-4"><code x-ref="configCode" class="language-nginx !bg-transparent">{{ $nginxConfig }}</code></pre>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                </div>
            @endforeach
        </div>
    @endif
</div>

@script
<script>
    const crosshairPlugin = {
        id: 'crosshair',
        afterDraw(chart) {
            if (chart.tooltip._active && chart.tooltip._active.length) {
                const ctx = chart.ctx;
                const x = chart.tooltip._active[0].element.x;
                const topY = chart.scales.y.top;
                const bottomY = chart.scales.y.bottom;
                ctx.save();
                ctx.beginPath();
                ctx.moveTo(x, topY);
                ctx.lineTo(x, bottomY);
                ctx.lineWidth = 1;
                ctx.strokeStyle = 'rgba(156, 163, 175, 0.25)';
                ctx.stroke();
                ctx.restore();
            }
        },
    };

    let siteChart = null;

    function initSiteChart() {
        const dataEl = document.getElementById('siteHistoryData');
        const canvas = document.getElementById('siteHistoryChart');

        if (!dataEl || !canvas) {
            if (siteChart) { siteChart.destroy(); siteChart = null; }
            return;
        }

        const history = JSON.parse(dataEl.dataset.history);

        if (siteChart) { siteChart.destroy(); siteChart = null; }
        if (!history.length) return;

        siteChart = new Chart(canvas, {
            type: 'line',
            data: {
                labels: history.map(h => h.time),
                datasets: [{
                    label: 'Response',
                    data: history.map(h => h.response_ms),
                    borderColor: 'rgb(96, 165, 250)',
                    backgroundColor: 'rgba(96, 165, 250, 0.1)',
                    fill: true,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#111827',
                        borderColor: '#374151',
                        borderWidth: 1,
                        titleColor: '#9ca3af',
                        bodyColor: '#e5e7eb',
                        padding: 10,
                        titleFont: { family: 'monospace', size: 11 },
                        bodyFont: { family: 'monospace', size: 11 },
                        callbacks: { label: ctx => ctx.parsed.y + 'ms' },
                    },
                },
                scales: {
                    x: {
                        display: true,
                        ticks: { color: '#4b5563', font: { size: 10, family: 'monospace' }, maxTicksLimit: 8, maxRotation: 0 },
                        grid: { color: 'rgba(75, 85, 99, 0.2)' },
                    },
                    y: {
                        display: true,
                        min: 0,
                        ticks: { color: '#4b5563', font: { size: 10 }, callback: v => v + 'ms', maxTicksLimit: 5 },
                        grid: { color: 'rgba(75, 85, 99, 0.2)' },
                    },
                },
                elements: { line: { tension: 0.4, borderWidth: 1.5 } },
            },
            plugins: [crosshairPlugin],
        });
    }

    initSiteChart();

    new MutationObserver(initSiteChart).observe($el, {
        subtree: true,
        childList: true,
        attributes: true,
        attributeFilter: ['data-history'],
    });
</script>
@endscript
