<div>
    @if($killMessage)
        <div class="mb-4 px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-300 font-mono">
            {{ $killMessage }}
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-2 mb-6">
        <h1 class="text-xl font-bold text-gray-100">Resources</h1>
        <div class="flex gap-1">
            @foreach(['1h' => '1h', '24h' => '24h', '7d' => '7d'] as $value => $label)
                <button wire:click="setRange('{{ $value }}')"
                        class="px-3 py-1 text-xs font-mono rounded transition-colors {{ $range === $value ? 'bg-gray-700 text-gray-100' : 'text-gray-500 hover:text-gray-300' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- CPU --}}
    <div class="bg-gray-900 border border-gray-800 rounded-lg p-5 mb-4">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between mb-4 gap-4">
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">CPU</p>
                <p class="text-3xl font-bold text-gray-100">{{ number_format($cpuPercent, 1) }}<span class="text-lg text-gray-500 ml-1">%</span></p>
                @if($uptime)
                    <p class="text-xs text-gray-600 font-mono mt-0.5">up {{ $uptime }}</p>
                @endif
            </div>
            <div class="grid grid-cols-4 gap-3 sm:flex sm:gap-8 sm:text-right">
                <div>
                    <p class="text-xs text-gray-500 mb-1">Load 1m</p>
                    <p class="text-sm font-mono {{ $loadAverage['one'] > $coreCount ? 'text-red-400' : 'text-gray-100' }}">{{ $loadAverage['one'] }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 mb-1">Load 5m</p>
                    <p class="text-sm font-mono {{ $loadAverage['five'] > $coreCount ? 'text-red-400' : 'text-gray-100' }}">{{ $loadAverage['five'] }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 mb-1">Load 15m</p>
                    <p class="text-sm font-mono {{ $loadAverage['fifteen'] > $coreCount ? 'text-red-400' : 'text-gray-100' }}">{{ $loadAverage['fifteen'] }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 mb-1">Cores</p>
                    <p class="text-sm font-mono text-gray-100">{{ $coreCount }}</p>
                </div>
            </div>
        </div>
        <div class="mb-3 h-1.5 bg-gray-800 rounded-full overflow-hidden">
            <div class="h-full rounded-full transition-all duration-500 {{ $cpuPercent > 80 ? 'bg-red-500' : ($cpuPercent > 50 ? 'bg-amber-500' : 'bg-green-500') }}"
                 style="width: {{ min($cpuPercent, 100) }}%"></div>
        </div>
        <div class="h-24" wire:ignore>
            <canvas id="cpuChart"></canvas>
        </div>
    </div>

    {{-- RAM --}}
    <div class="bg-gray-900 border border-gray-800 rounded-lg p-5 mb-4">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between mb-4 gap-4">
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">RAM</p>
                <p class="text-3xl font-bold text-gray-100">{{ number_format($ram['used_mb'] / 1024, 1) }}<span class="text-lg text-gray-500 ml-1">GB</span></p>
                <p class="text-sm text-gray-500 mt-0.5">of {{ number_format($ram['total_mb'] / 1024, 1) }} GB</p>
            </div>
            <div class="grid grid-cols-4 gap-3 sm:flex sm:gap-8 sm:text-right">
                <div>
                    <p class="text-xs text-gray-500 mb-1">Free</p>
                    <p class="text-sm font-mono text-gray-100">{{ number_format($ram['free_mb'] / 1024, 1) }} GB</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 mb-1">Cached</p>
                    <p class="text-sm font-mono text-gray-100">{{ number_format($ram['cached_mb'] / 1024, 1) }} GB</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 mb-1">Swap Used</p>
                    <p class="text-sm font-mono {{ $swap['used_mb'] > 0 ? 'text-amber-400' : 'text-gray-100' }}">
                        {{ number_format($swap['used_mb'] / 1024, 2) }} GB
                    </p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 mb-1">Swap Total</p>
                    <p class="text-sm font-mono text-gray-100">{{ number_format($swap['total_mb'] / 1024, 1) }} GB</p>
                </div>
            </div>
        </div>
        @php $ramPercent = $ram['total_mb'] > 0 ? ($ram['used_mb'] / $ram['total_mb']) * 100 : 0; @endphp
        <div class="mb-3 h-1.5 bg-gray-800 rounded-full overflow-hidden">
            <div class="h-full rounded-full transition-all duration-500 {{ $ramPercent > 80 ? 'bg-red-500' : ($ramPercent > 50 ? 'bg-amber-500' : 'bg-blue-400') }}"
                 style="width: {{ min($ramPercent, 100) }}%"></div>
        </div>
        <div class="h-24" wire:ignore>
            <canvas id="ramChart"></canvas>
        </div>
    </div>

    {{-- Disk --}}
    <div class="bg-gray-900 border border-gray-800 rounded-lg p-5 mb-4">
        <div class="flex items-center justify-between mb-3">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Disk</p>
            <div class="flex gap-4">
                <span class="text-xs font-mono text-gray-600">R {{ $diskIo['read_kbps'] }} KB/s</span>
                <span class="text-xs font-mono text-gray-600">W {{ $diskIo['write_kbps'] }} KB/s</span>
            </div>
        </div>
        @if(!empty($diskPartitions))
            <div class="space-y-3">
                @foreach($diskPartitions as $partition)
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <div class="flex items-center gap-3 min-w-0">
                                <p class="text-sm font-mono text-gray-100">{{ $partition['mount'] }}</p>
                                <p class="text-xs text-gray-600 font-mono hidden sm:block truncate">{{ $partition['device'] }}</p>
                            </div>
                            <p class="text-xs text-gray-500 flex-shrink-0 ml-3">{{ $partition['used_gb'] }} / {{ $partition['total_gb'] }} GB &middot; {{ $partition['percent'] }}%</p>
                        </div>
                        <div class="h-1.5 bg-gray-800 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-500 {{ $partition['percent'] > 80 ? 'bg-red-500' : ($partition['percent'] > 60 ? 'bg-amber-500' : 'bg-green-500') }}"
                                 style="width: {{ min($partition['percent'], 100) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="flex items-center justify-between mb-3">
                <p class="text-3xl font-bold text-gray-100">{{ number_format($disk['used_gb'], 0) }}<span class="text-lg text-gray-500 ml-1">GB</span></p>
                <p class="text-sm text-gray-500">{{ number_format($disk['percent'], 1) }}% of {{ number_format($disk['total_gb'], 0) }} GB</p>
            </div>
            <div class="h-2 bg-gray-800 rounded-full overflow-hidden">
                <div class="h-full rounded-full transition-all duration-500 {{ $disk['percent'] > 80 ? 'bg-red-500' : ($disk['percent'] > 60 ? 'bg-amber-500' : 'bg-green-500') }}"
                     style="width: {{ min($disk['percent'], 100) }}%"></div>
            </div>
        @endif
    </div>

    {{-- Network --}}
    <div class="bg-gray-900 border border-gray-800 rounded-lg p-5 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-4">
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Network</p>
                <p class="text-xs text-gray-600 font-mono">{{ $network['interface'] }}</p>
            </div>
            <div class="grid grid-cols-3 gap-x-6 gap-y-3 sm:flex sm:gap-8 sm:text-right">
                <div>
                    <p class="text-xs text-gray-500 mb-1">↓ In</p>
                    <p class="text-sm font-mono text-gray-100">{{ $network['rx_rate_kbps'] }} KB/s</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 mb-1">↑ Out</p>
                    <p class="text-sm font-mono text-gray-100">{{ $network['tx_rate_kbps'] }} KB/s</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 mb-1">Total In</p>
                    <p class="text-sm font-mono text-gray-100">{{ $network['rx_total_gb'] }} GB</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 mb-1">Total Out</p>
                    <p class="text-sm font-mono text-gray-100">{{ $network['tx_total_gb'] }} GB</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 mb-1">TCP Estab</p>
                    <p class="text-sm font-mono text-gray-100">{{ $tcpStats['established'] }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 mb-1">Time-Wait</p>
                    <p class="text-sm font-mono {{ $tcpStats['time_wait'] > 100 ? 'text-amber-400' : 'text-gray-100' }}">{{ $tcpStats['time_wait'] }}</p>
                </div>
            </div>
        </div>
        <div class="h-24" wire:ignore>
            <canvas id="networkChart"></canvas>
        </div>
    </div>

    {{-- Top Processes --}}
    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
        <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider">Top Processes</h2>
        <div class="flex gap-1">
            <button wire:click="setProcessSort('cpu')"
                    class="px-3 py-1 text-xs font-mono rounded transition-colors {{ $processSort === 'cpu' ? 'bg-gray-700 text-gray-100' : 'text-gray-500 hover:text-gray-300' }}">
                CPU
            </button>
            <button wire:click="setProcessSort('memory')"
                    class="px-3 py-1 text-xs font-mono rounded transition-colors {{ $processSort === 'memory' ? 'bg-gray-700 text-gray-100' : 'text-gray-500 hover:text-gray-300' }}">
                MEM
            </button>
        </div>
    </div>
    <div class="overflow-x-auto">
    <div class="bg-gray-900 border border-gray-800 rounded-lg overflow-hidden min-w-[560px]">
        <div class="px-5 py-2.5 grid grid-cols-12 gap-4 border-b border-gray-800">
            <p class="text-xs text-gray-600 uppercase tracking-wider col-span-1">PID</p>
            <p class="text-xs text-gray-600 uppercase tracking-wider col-span-2">User</p>
            <p class="text-xs {{ $processSort === 'cpu' ? 'text-gray-300' : 'text-gray-600' }} uppercase tracking-wider col-span-1 text-right">CPU%</p>
            <p class="text-xs {{ $processSort === 'memory' ? 'text-gray-300' : 'text-gray-600' }} uppercase tracking-wider col-span-1 text-right">MEM%</p>
            <p class="text-xs text-gray-600 uppercase tracking-wider col-span-6">Command</p>
            <p class="text-xs text-gray-600 uppercase tracking-wider col-span-1 text-right">Kill</p>
        </div>
        @foreach($processes as $proc)
            <div class="px-5 py-2.5 grid grid-cols-12 gap-4 border-t border-gray-800/60 items-center">
                <p class="text-xs font-mono text-gray-500 col-span-1">{{ $proc['pid'] }}</p>
                <p class="text-xs font-mono text-gray-400 col-span-2 truncate">{{ $proc['user'] }}</p>
                <p class="text-xs font-mono col-span-1 text-right {{ $proc['cpu'] > 50 ? 'text-red-400' : ($proc['cpu'] > 20 ? 'text-amber-400' : 'text-gray-300') }}">{{ $proc['cpu'] }}</p>
                <p class="text-xs font-mono col-span-1 text-right {{ $proc['memory'] > 20 ? 'text-amber-400' : 'text-gray-300' }}">{{ $proc['memory'] }}</p>
                <p class="text-xs font-mono text-gray-400 col-span-6 truncate">{{ $proc['command'] }}</p>
                <div class="col-span-1 flex justify-end">
                    <button wire:click="kill({{ $proc['pid'] }})"
                            wire:confirm="Send SIGTERM to PID {{ $proc['pid'] }}?"
                            class="text-xs font-mono text-gray-700 hover:text-red-400 transition-colors">
                        kill
                    </button>
                </div>
            </div>
        @endforeach
    </div>
    </div>

    {{-- Chart data updated by Livewire on each render --}}
    <div id="resourceChartData"
         data-cpu="{{ json_encode($cpuHistory) }}"
         data-ram="{{ json_encode($ramHistory) }}"
         data-net-rx="{{ json_encode($netRxHistory) }}"
         data-net-tx="{{ json_encode($netTxHistory) }}"
         data-labels="{{ json_encode($labels) }}">
    </div>
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

    const chartOptions = {
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
                max: 100,
                ticks: { color: '#4b5563', font: { size: 10 }, callback: v => v + '%', maxTicksLimit: 5 },
                grid: { color: 'rgba(75, 85, 99, 0.2)' },
            },
        },
        elements: { point: { radius: 0, hoverRadius: 4 }, line: { tension: 0.4, borderWidth: 1.5 } },
    };

    const readData = () => {
        const el = document.getElementById('resourceChartData');
        return {
            cpu: JSON.parse(el.dataset.cpu),
            ram: JSON.parse(el.dataset.ram),
            netRx: JSON.parse(el.dataset.netRx),
            netTx: JSON.parse(el.dataset.netTx),
            labels: JSON.parse(el.dataset.labels),
        };
    };

    const networkChartOptions = {
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
                callbacks: { label: ctx => ctx.dataset.label + ': ' + ctx.parsed.y + ' KB/s' },
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
                ticks: { color: '#4b5563', font: { size: 10 }, callback: v => v + ' KB/s', maxTicksLimit: 5 },
                grid: { color: 'rgba(75, 85, 99, 0.2)' },
            },
        },
        elements: { point: { radius: 0, hoverRadius: 4 }, line: { tension: 0.4, borderWidth: 1.5 } },
    };

    const initial = readData();

    const cpuChart = new Chart(document.getElementById('cpuChart'), {
        type: 'line',
        data: { labels: initial.labels, datasets: [{ label: 'CPU', data: initial.cpu, borderColor: 'rgb(74, 222, 128)', backgroundColor: 'rgba(74, 222, 128, 0.1)', fill: true }] },
        options: chartOptions,
        plugins: [crosshairPlugin],
    });

    const ramChart = new Chart(document.getElementById('ramChart'), {
        type: 'line',
        data: { labels: initial.labels, datasets: [{ label: 'RAM', data: initial.ram, borderColor: 'rgb(96, 165, 250)', backgroundColor: 'rgba(96, 165, 250, 0.1)', fill: true }] },
        options: chartOptions,
        plugins: [crosshairPlugin],
    });

    const networkChart = new Chart(document.getElementById('networkChart'), {
        type: 'line',
        data: {
            labels: initial.labels,
            datasets: [
                { label: '↓ In', data: initial.netRx, borderColor: 'rgb(74, 222, 128)', backgroundColor: 'rgba(74, 222, 128, 0.1)', fill: true, pointRadius: 0, pointHoverRadius: 4 },
                { label: '↑ Out', data: initial.netTx, borderColor: 'rgb(251, 146, 60)', backgroundColor: 'rgba(251, 146, 60, 0.1)', fill: true, pointRadius: 0, pointHoverRadius: 4 },
            ],
        },
        options: networkChartOptions,
        plugins: [crosshairPlugin],
    });

    new MutationObserver(() => {
        const d = readData();
        cpuChart.data.labels = d.labels;
        cpuChart.data.datasets[0].data = d.cpu;
        cpuChart.update('none');
        ramChart.data.labels = d.labels;
        ramChart.data.datasets[0].data = d.ram;
        ramChart.update('none');
        networkChart.data.labels = d.labels;
        networkChart.data.datasets[0].data = d.netRx;
        networkChart.data.datasets[1].data = d.netTx;
        networkChart.update('none');
    }).observe(document.getElementById('resourceChartData'), { attributes: true });
</script>
@endscript
