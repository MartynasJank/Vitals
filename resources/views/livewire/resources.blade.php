<div>
    <div class="flex items-center justify-between mb-6">
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
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-3">Disk</p>
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
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Network</p>
                <p class="text-xs text-gray-600 font-mono">{{ $network['interface'] }}</p>
            </div>
            <div class="grid grid-cols-2 gap-x-6 gap-y-3 sm:flex sm:gap-8 sm:text-right">
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
            </div>
        </div>
    </div>

    {{-- Top Processes --}}
    <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-3">Top Processes</h2>
    <div class="overflow-x-auto">
    <div class="bg-gray-900 border border-gray-800 rounded-lg overflow-hidden min-w-[560px]">
        <div class="px-5 py-2.5 grid grid-cols-12 gap-4 border-b border-gray-800">
            <p class="text-xs text-gray-600 uppercase tracking-wider col-span-1">PID</p>
            <p class="text-xs text-gray-600 uppercase tracking-wider col-span-2">User</p>
            <p class="text-xs text-gray-600 uppercase tracking-wider col-span-1 text-right">CPU%</p>
            <p class="text-xs text-gray-600 uppercase tracking-wider col-span-1 text-right">MEM%</p>
            <p class="text-xs text-gray-600 uppercase tracking-wider col-span-7">Command</p>
        </div>
        @foreach($processes as $proc)
            <div class="px-5 py-2.5 grid grid-cols-12 gap-4 border-t border-gray-800/60">
                <p class="text-xs font-mono text-gray-500 col-span-1">{{ $proc['pid'] }}</p>
                <p class="text-xs font-mono text-gray-400 col-span-2 truncate">{{ $proc['user'] }}</p>
                <p class="text-xs font-mono col-span-1 text-right {{ $proc['cpu'] > 50 ? 'text-red-400' : ($proc['cpu'] > 20 ? 'text-amber-400' : 'text-gray-300') }}">{{ $proc['cpu'] }}</p>
                <p class="text-xs font-mono col-span-1 text-right {{ $proc['memory'] > 20 ? 'text-amber-400' : 'text-gray-300' }}">{{ $proc['memory'] }}</p>
                <p class="text-xs font-mono text-gray-400 col-span-7 truncate">{{ $proc['command'] }}</p>
            </div>
        @endforeach
    </div>
    </div>
</div>

{{-- Chart data updated by Livewire on each render --}}
<div id="resourceChartData"
     data-cpu="{{ json_encode($cpuHistory) }}"
     data-ram="{{ json_encode($ramHistory) }}"
     data-labels="{{ json_encode($labels) }}">
</div>

@script
<script>
    const chartOptions = {
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
                max: 100,
                ticks: { color: '#4b5563', font: { size: 10 }, callback: v => v + '%', maxTicksLimit: 5 },
                grid: { color: 'rgba(75, 85, 99, 0.2)' },
            },
        },
        elements: { point: { radius: 0 }, line: { tension: 0.4, borderWidth: 1.5 } },
    };

    const readData = () => {
        const el = document.getElementById('resourceChartData');
        return {
            cpu: JSON.parse(el.dataset.cpu),
            ram: JSON.parse(el.dataset.ram),
            labels: JSON.parse(el.dataset.labels),
        };
    };

    const initial = readData();

    const cpuChart = new Chart(document.getElementById('cpuChart'), {
        type: 'line',
        data: { labels: initial.labels, datasets: [{ data: initial.cpu, borderColor: 'rgb(74, 222, 128)', backgroundColor: 'rgba(74, 222, 128, 0.1)', fill: true }] },
        options: chartOptions,
    });

    const ramChart = new Chart(document.getElementById('ramChart'), {
        type: 'line',
        data: { labels: initial.labels, datasets: [{ data: initial.ram, borderColor: 'rgb(96, 165, 250)', backgroundColor: 'rgba(96, 165, 250, 0.1)', fill: true }] },
        options: chartOptions,
    });

    document.addEventListener('livewire:updated', () => {
        const d = readData();
        cpuChart.data.labels = d.labels;
        cpuChart.data.datasets[0].data = d.cpu;
        cpuChart.update('none');
        ramChart.data.labels = d.labels;
        ramChart.data.datasets[0].data = d.ram;
        ramChart.update('none');
    });
</script>
@endscript
