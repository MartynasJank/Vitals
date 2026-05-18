<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold text-gray-100">Dashboard</h1>
        <div x-data="{ elapsed: 0 }"
             x-init="setInterval(() => elapsed++, 1000)"
             @dashboard-refreshed.window="elapsed = 0">
            <span class="text-xs text-gray-600 font-mono" x-text="'updated ' + elapsed + 's ago'"></span>
        </div>
    </div>

    {{-- Alerts --}}
    <div class="mb-6">
        @if(empty($alerts))
            <div class="flex items-center gap-3 px-4 py-3 rounded-lg border border-green-900/30 bg-green-950/20">
                <span class="w-2 h-2 rounded-full bg-green-400 flex-shrink-0"></span>
                <p class="text-sm text-green-400">All systems normal</p>
            </div>
        @else
            <div class="space-y-2">
                @foreach($alerts as $alert)
                    <div class="flex items-center gap-3 px-4 py-3 rounded-lg border
                        {{ $alert['level'] === 'error' ? 'bg-red-950/40 border-red-900/50' : 'bg-amber-950/40 border-amber-900/50' }}">
                        <span class="w-2 h-2 rounded-full flex-shrink-0 {{ $alert['level'] === 'error' ? 'bg-red-500' : 'bg-amber-500' }}"></span>
                        <p class="text-sm {{ $alert['level'] === 'error' ? 'text-red-300' : 'text-amber-300' }}">{{ $alert['message'] }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Stat cards --}}
    @php
        $fmtKbps = fn ($kbps) => $kbps >= 1024
            ? number_format($kbps / 1024, 1).' MB/s'
            : number_format($kbps, 0).' KB/s';
    @endphp

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">

        {{-- CPU --}}
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-3">CPU</p>
            <p class="text-4xl font-bold text-gray-100">{{ number_format($cpuPercent, 1) }}<span class="text-xl text-gray-500 ml-1">%</span></p>
            <div class="mt-4 h-1.5 bg-gray-800 rounded-full overflow-hidden">
                <div class="h-full rounded-full transition-all duration-500
                    {{ $cpuPercent > 80 ? 'bg-red-500' : ($cpuPercent > 50 ? 'bg-amber-500' : 'bg-green-500') }}"
                    style="width: {{ min($cpuPercent, 100) }}%">
                </div>
            </div>
            <div class="mt-3 h-12" wire:ignore>
                <canvas id="cpuChart"></canvas>
            </div>
        </div>

        {{-- RAM --}}
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-3">RAM</p>
            <p class="text-4xl font-bold text-gray-100">
                {{ number_format($ramUsedMb / 1024, 1) }}<span class="text-xl text-gray-500 ml-1">GB</span>
            </p>
            <p class="text-sm text-gray-500 mt-1">of {{ number_format($ramTotalMb / 1024, 1) }} GB</p>
            @php $ramPercent = $ramTotalMb > 0 ? ($ramUsedMb / $ramTotalMb) * 100 : 0; @endphp
            <div class="mt-3 h-1.5 bg-gray-800 rounded-full overflow-hidden">
                <div class="h-full rounded-full transition-all duration-500
                    {{ $ramPercent > 80 ? 'bg-red-500' : ($ramPercent > 50 ? 'bg-amber-500' : 'bg-green-500') }}"
                    style="width: {{ min($ramPercent, 100) }}%">
                </div>
            </div>
            <div class="mt-3 h-12" wire:ignore>
                <canvas id="ramChart"></canvas>
            </div>
        </div>

        {{-- Disk --}}
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-3">Disk</p>
            <p class="text-4xl font-bold text-gray-100">
                {{ number_format($diskUsedGb, 0) }}<span class="text-xl text-gray-500 ml-1">GB</span>
            </p>
            <p class="text-sm text-gray-500 mt-1">of {{ number_format($diskTotalGb, 0) }} GB</p>
            <div class="mt-3 h-1.5 bg-gray-800 rounded-full overflow-hidden">
                <div class="h-full rounded-full transition-all duration-500
                    {{ $diskPercent > 80 ? 'bg-red-500' : ($diskPercent > 50 ? 'bg-amber-500' : 'bg-green-500') }}"
                    style="width: {{ min($diskPercent, 100) }}%">
                </div>
            </div>
            <div class="mt-3 h-12" wire:ignore>
                <canvas id="diskChart"></canvas>
            </div>
        </div>

        {{-- Network I/O --}}
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-3">Network I/O</p>
            <p class="text-2xl font-bold text-gray-100">↓ {{ $fmtKbps($networkStats['rx_rate_kbps']) }}</p>
            <p class="text-sm text-gray-500 mt-1">↑ {{ $fmtKbps($networkStats['tx_rate_kbps']) }}</p>
            <div class="mt-4 h-12" wire:ignore>
                <canvas id="networkChart"></canvas>
            </div>
        </div>

    </div>

    {{-- Quick info pills --}}
    <div class="flex flex-wrap gap-3 mb-6">
        <div class="bg-gray-900 border border-gray-800 rounded-lg px-4 py-2.5 flex items-center gap-2.5">
            <p class="text-xs text-gray-500">Uptime</p>
            <p class="text-sm font-mono text-gray-100">{{ $uptime }}</p>
        </div>
        <div class="bg-gray-900 border border-gray-800 rounded-lg px-4 py-2.5 flex items-center gap-2.5">
            <p class="text-xs text-gray-500">Load</p>
            <p class="text-sm font-mono {{ $loadAverage['one'] > $coreCount ? 'text-amber-400' : 'text-gray-100' }}">{{ $loadAverage['one'] }}</p>
        </div>
        <div class="bg-gray-900 border border-gray-800 rounded-lg px-4 py-2.5 flex items-center gap-2.5">
            <p class="text-xs text-gray-500">Swap</p>
            <p class="text-sm font-mono {{ $swap['used_mb'] > 0 ? 'text-amber-400' : 'text-gray-100' }}">
                {{ number_format($swap['used_mb'] / 1024, 1) }} / {{ number_format($swap['total_mb'] / 1024, 1) }} GB
            </p>
        </div>
        <div class="bg-gray-900 border border-gray-800 rounded-lg px-4 py-2.5 flex items-center gap-2.5">
            <p class="text-xs text-gray-500">TCP</p>
            <p class="text-sm font-mono text-gray-100">{{ number_format($tcpStats['established']) }} est.</p>
        </div>
        <a href="{{ route('threat-intel') }}" class="bg-gray-900 border border-gray-800 rounded-lg px-4 py-2.5 flex items-center gap-2.5 hover:border-gray-700 transition-colors">
            <p class="text-xs text-gray-500">Attacks 1h</p>
            <p class="text-sm font-mono {{ $attacksLastHour > 100 ? 'text-red-400' : ($attacksLastHour > 20 ? 'text-amber-400' : 'text-green-400') }}">
                {{ number_format($attacksLastHour) }}
            </p>
        </a>
        <a href="{{ route('threat-intel') }}" class="bg-gray-900 border border-gray-800 rounded-lg px-4 py-2.5 flex items-center gap-2.5 hover:border-gray-700 transition-colors">
            <p class="text-xs text-gray-500">Attacks 24h</p>
            <p class="text-sm font-mono {{ $attacksLast24h > 500 ? 'text-red-400' : ($attacksLast24h > 100 ? 'text-amber-400' : 'text-green-400') }}">
                {{ number_format($attacksLast24h) }}
            </p>
        </a>
    </div>

    {{-- Sites + Services --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        {{-- Sites --}}
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-5">
            <div class="flex items-center justify-between mb-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Sites</p>
                <a href="{{ route('sites') }}" class="text-xs text-gray-600 hover:text-gray-400 transition-colors font-mono">view all →</a>
            </div>
            @if(empty($siteStatuses))
                <p class="text-sm text-gray-600 font-mono">No data yet — run <span class="text-gray-500">vitals:check-sites</span></p>
            @else
                <div class="space-y-3">
                    @foreach($siteStatuses as $site)
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <span class="w-2 h-2 rounded-full flex-shrink-0 {{ $site['status'] === 'up' ? 'bg-green-400' : 'bg-red-500' }}"></span>
                                <p class="text-sm font-mono text-gray-300 truncate">{{ $site['site_name'] }}</p>
                            </div>
                            <div class="flex items-center gap-3 flex-shrink-0 ml-3">
                                <p class="text-xs text-gray-500 font-mono">{{ $site['response_ms'] ? $site['response_ms'].'ms' : '—' }}</p>
                                <span class="text-xs font-medium {{ $site['status'] === 'up' ? 'text-green-400' : 'text-red-400' }}">
                                    {{ strtoupper($site['status']) }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Services --}}
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-5">
            <div class="flex items-center justify-between mb-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Services</p>
                <a href="{{ route('services') }}" class="text-xs text-gray-600 hover:text-gray-400 transition-colors font-mono">view all →</a>
            </div>
            <div class="space-y-3">
                @foreach($services as $service)
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <span class="w-2 h-2 rounded-full flex-shrink-0 {{ $service['restarting'] ? 'bg-amber-400' : ($service['running'] ? 'bg-green-400' : 'bg-red-500') }}"></span>
                            <p class="text-sm text-gray-300">{{ $service['label'] }}</p>
                        </div>
                        <span class="text-xs font-mono {{ $service['restarting'] ? 'text-amber-400' : ($service['running'] ? 'text-green-400' : 'text-red-400') }}">
                            {{ $service['restarting'] ? 'restarting…' : ($service['running'] ? 'running' : 'stopped') }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

    </div>

    {{-- Top IPs widget --}}
    <div class="bg-gray-900 border border-gray-800 rounded-lg mt-4">
        <div class="px-5 py-4 border-b border-gray-800 flex items-center justify-between">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Top IPs by Hit Count</p>
            <div class="flex gap-1">
                @foreach(['24h' => '24h', '7d' => '7d', '30d' => '30d'] as $value => $label)
                    <button wire:click="setTopIpsRange('{{ $value }}')"
                            class="px-3 py-1 text-xs font-mono rounded transition-colors {{ $topIpsRange === $value ? 'bg-gray-700 text-gray-100' : 'text-gray-500 hover:text-gray-300' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>
        @if(empty($topIps))
            <div class="p-5">
                <p class="text-sm text-gray-600 font-mono">No attack data for this period.</p>
            </div>
        @else
            <div class="divide-y divide-gray-800">
                @foreach($topIps as $i => $entry)
                    <div class="px-5 py-2.5 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="text-xs font-mono text-gray-600 w-4 text-right flex-shrink-0">{{ $i + 1 }}</span>
                            <a href="{{ route('ip-detail', $entry['ip']) }}"
                               class="text-sm font-mono text-red-400 hover:text-red-300 hover:underline transition-colors flex-shrink-0">{{ $entry['ip'] }}</a>
                            @if($entry['country_code'])
                                <img src="https://flagcdn.com/16x12/{{ $entry['country_code'] }}.png"
                                     alt="{{ $entry['country'] ?? '' }}"
                                     class="w-4 h-3 object-cover rounded-sm opacity-70 flex-shrink-0">
                            @endif
                            @if($entry['isp'])
                                <span class="text-xs font-mono text-gray-600 truncate">{{ $entry['isp'] }}</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            @if($entry['ssh'] > 0)
                                <span class="text-xs font-mono text-red-400/70">SSH {{ number_format($entry['ssh']) }}</span>
                            @endif
                            @if($entry['nginx'] > 0)
                                <span class="text-xs font-mono text-amber-400/70">Nginx {{ number_format($entry['nginx']) }}</span>
                            @endif
                            <span class="text-sm font-mono font-bold {{ $entry['total'] > 10000 ? 'text-red-400' : ($entry['total'] > 1000 ? 'text-amber-400' : 'text-gray-300') }}">
                                {{ number_format($entry['total']) }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Chart data updated by Livewire on each render --}}
    <div id="dashChartData"
         data-cpu="{{ json_encode($cpuHistory) }}"
         data-ram="{{ json_encode($ramHistory) }}"
         data-disk="{{ json_encode($diskHistory) }}"
         data-net-rx="{{ json_encode($netRxHistory) }}"
         data-net-tx="{{ json_encode($netTxHistory) }}"
         data-labels="{{ json_encode($labels) }}"
         class="hidden">
    </div>
</div>

@script
<script>
    const sparklineOptions = {
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
                padding: 8,
                titleFont: { family: 'monospace', size: 11 },
                bodyFont: { family: 'monospace', size: 11 },
            },
        },
        scales: {
            x: { display: false },
            y: { display: false, min: 0 },
        },
        elements: {
            point: { radius: 0, hoverRadius: 3 },
            line: { tension: 0.4, borderWidth: 1.5 },
        },
    };

    const readData = () => {
        const el = document.getElementById('dashChartData');
        return {
            cpu: JSON.parse(el.dataset.cpu),
            ram: JSON.parse(el.dataset.ram),
            disk: JSON.parse(el.dataset.disk),
            netRx: JSON.parse(el.dataset.netRx),
            netTx: JSON.parse(el.dataset.netTx),
            labels: JSON.parse(el.dataset.labels),
        };
    };

    const initial = readData();

    const cpuChart = new Chart(document.getElementById('cpuChart'), {
        type: 'line',
        data: {
            labels: initial.labels,
            datasets: [{ data: initial.cpu, borderColor: '#4ade80', backgroundColor: 'rgba(74,222,128,0.1)', fill: true }],
        },
        options: sparklineOptions,
    });

    const ramChart = new Chart(document.getElementById('ramChart'), {
        type: 'line',
        data: {
            labels: initial.labels,
            datasets: [{ data: initial.ram, borderColor: '#60a5fa', backgroundColor: 'rgba(96,165,250,0.1)', fill: true }],
        },
        options: sparklineOptions,
    });

    const diskChart = new Chart(document.getElementById('diskChart'), {
        type: 'line',
        data: {
            labels: initial.labels,
            datasets: [{ data: initial.disk, borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,0.1)', fill: true }],
        },
        options: sparklineOptions,
    });

    const networkChart = new Chart(document.getElementById('networkChart'), {
        type: 'line',
        data: {
            labels: initial.labels,
            datasets: [
                { data: initial.netRx, borderColor: '#4ade80', backgroundColor: 'rgba(74,222,128,0.05)', fill: true },
                { data: initial.netTx, borderColor: '#fb923c', backgroundColor: 'rgba(251,146,60,0.05)', fill: true },
            ],
        },
        options: sparklineOptions,
    });

    new MutationObserver(() => {
        const d = readData();
        cpuChart.data.labels = d.labels;
        cpuChart.data.datasets[0].data = d.cpu;
        cpuChart.update('none');
        ramChart.data.labels = d.labels;
        ramChart.data.datasets[0].data = d.ram;
        ramChart.update('none');
        diskChart.data.labels = d.labels;
        diskChart.data.datasets[0].data = d.disk;
        diskChart.update('none');
        networkChart.data.labels = d.labels;
        networkChart.data.datasets[0].data = d.netRx;
        networkChart.data.datasets[1].data = d.netTx;
        networkChart.update('none');
    }).observe(document.getElementById('dashChartData'), { attributes: true });
</script>
@endscript