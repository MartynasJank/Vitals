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
                <div class="bg-gray-900 border border-gray-800 rounded-lg overflow-hidden">

                    {{-- Site row (clickable to expand) --}}
                    <div wire:click="selectSite('{{ $site['url'] }}')"
                         class="w-full px-4 sm:px-5 py-4 flex items-center justify-between gap-4 hover:bg-gray-800/30 transition-colors cursor-pointer">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="w-2 h-2 rounded-full flex-shrink-0 {{ $site['status'] === 'up' ? 'bg-green-400' : 'bg-red-500' }}"></span>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <p class="text-sm font-medium text-gray-100">{{ $site['name'] }}</p>
                                    @if(isset($site['ssl_days']))
                                        @if($site['ssl_days'] === null)
                                            <span class="text-xs font-mono text-gray-600">no SSL</span>
                                        @else
                                            <span class="text-xs font-mono px-1.5 py-0.5 rounded
                                                {{ $site['ssl_days'] < 14 ? 'text-red-400 bg-red-950/50' : ($site['ssl_days'] < 30 ? 'text-amber-400 bg-amber-950/50' : 'text-green-400 bg-green-950/50') }}">
                                                SSL {{ $site['ssl_days'] }}d
                                            </span>
                                        @endif
                                    @endif
                                </div>
                                <p class="text-xs text-gray-500 font-mono truncate">{{ $site['url'] }}</p>
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
                                <p class="text-sm text-gray-300">
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

                            {{-- Response time chart --}}
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-3">Response Time History</p>

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
                                <div x-data="{ open: false }">
                                    <button @click="open = !open"
                                            class="flex items-center gap-2 text-xs text-gray-500 hover:text-gray-300 transition-colors font-mono">
                                        <svg class="w-3 h-3 transition-transform" :class="{ 'rotate-90': open }"
                                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                        nginx config
                                    </button>
                                    <div x-show="open" x-cloak class="mt-3">
                                        <pre class="text-xs font-mono text-gray-400 bg-gray-950 rounded-lg p-4 overflow-x-auto max-h-72 overflow-y-auto leading-relaxed">{{ $nginxConfig }}</pre>
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
                    data: history.map(h => h.response_ms),
                    borderColor: 'rgb(96, 165, 250)',
                    backgroundColor: 'rgba(96, 165, 250, 0.1)',
                    fill: true,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
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
                elements: { point: { radius: 0 }, line: { tension: 0.4, borderWidth: 1.5 } },
            },
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
