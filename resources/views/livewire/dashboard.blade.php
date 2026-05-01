<div>
    <h1 class="text-xl font-bold text-gray-100 mb-6">Dashboard</h1>

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
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
        {{-- CPU --}}
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-3">CPU Usage</p>
            <p class="text-4xl font-bold text-gray-100">{{ number_format($cpuPercent, 1) }}<span class="text-xl text-gray-500 ml-1">%</span></p>
            <div class="mt-4 h-1.5 bg-gray-800 rounded-full overflow-hidden">
                <div class="h-full rounded-full transition-all duration-500
                    {{ $cpuPercent > 80 ? 'bg-red-500' : ($cpuPercent > 50 ? 'bg-amber-500' : 'bg-green-500') }}"
                    style="width: {{ min($cpuPercent, 100) }}%">
                </div>
            </div>
            <div class="mt-4 h-12">
                <canvas id="cpuChart"></canvas>
            </div>
        </div>

        {{-- RAM --}}
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-3">RAM Usage</p>
            <p class="text-4xl font-bold text-gray-100">
                {{ number_format($ramUsedMb / 1024, 1) }}<span class="text-xl text-gray-500 ml-1">GB</span>
            </p>
            <p class="text-sm text-gray-500 mt-1">of {{ number_format($ramTotalMb / 1024, 1) }} GB</p>
            <div class="mt-3 h-1.5 bg-gray-800 rounded-full overflow-hidden">
                @php $ramPercent = $ramTotalMb > 0 ? ($ramUsedMb / $ramTotalMb) * 100 : 0; @endphp
                <div class="h-full rounded-full transition-all duration-500
                    {{ $ramPercent > 80 ? 'bg-red-500' : ($ramPercent > 50 ? 'bg-amber-500' : 'bg-green-500') }}"
                    style="width: {{ min($ramPercent, 100) }}%">
                </div>
            </div>
            <div class="mt-3 h-12">
                <canvas id="ramChart"></canvas>
            </div>
        </div>

        {{-- Disk --}}
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-3">Disk Usage</p>
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
</div>

@script
<script>
    const chartDefaults = {
        type: 'line',
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { display: false },
                y: { display: false, min: 0, max: 100 },
            },
            elements: {
                point: { radius: 0 },
                line: { tension: 0.4, borderWidth: 1.5 },
            },
        },
    };

    const cpuChart = new Chart(document.getElementById('cpuChart'), {
        ...chartDefaults,
        data: {
            labels: @json($cpuHistory->keys()),
            datasets: [{
                data: @json($cpuHistory),
                borderColor: '#4ade80',
                backgroundColor: 'rgba(74, 222, 128, 0.1)',
                fill: true,
            }],
        },
    });

    const ramChart = new Chart(document.getElementById('ramChart'), {
        ...chartDefaults,
        data: {
            labels: @json($ramHistory->keys()),
            datasets: [{
                data: @json($ramHistory),
                borderColor: '#60a5fa',
                backgroundColor: 'rgba(96, 165, 250, 0.1)',
                fill: true,
            }],
        },
    });
</script>
@endscript
