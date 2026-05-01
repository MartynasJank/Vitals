<div>
    <h1 class="text-xl font-bold text-gray-100 mb-6">Dashboard</h1>

    <div class="grid grid-cols-3 gap-4">
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