<div>
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('threat-intel') }}"
           class="text-gray-600 hover:text-gray-300 transition-colors font-mono text-sm">← back</a>
        <h1 class="text-xl font-bold text-gray-100 font-mono">{{ $vhost }}</h1>
    </div>

    @if($totalHits === 0)
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-8 text-center">
            <p class="text-gray-500 font-mono text-sm">No hits recorded for this host.</p>
        </div>
    @else

        {{-- Summary cards --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
            <div class="bg-gray-900 border border-gray-800 rounded-lg p-4">
                <p class="text-xs text-gray-500 mb-1">Total Hits</p>
                <p class="text-2xl font-bold text-amber-400 font-mono">{{ number_format($totalHits) }}</p>
            </div>
            <div class="bg-gray-900 border border-gray-800 rounded-lg p-4">
                <p class="text-xs text-gray-500 mb-1">Unique IPs</p>
                <p class="text-2xl font-bold text-gray-100 font-mono">{{ number_format($uniqueIps) }}</p>
            </div>
            <div class="bg-gray-900 border border-gray-800 rounded-lg p-4">
                <p class="text-xs text-gray-500 mb-1">First Hit</p>
                <p class="text-sm font-mono text-gray-300 mt-1">{{ $firstHit ?? '—' }}</p>
            </div>
            <div class="bg-gray-900 border border-gray-800 rounded-lg p-4">
                <p class="text-xs text-gray-500 mb-1">Last Hit</p>
                <p class="text-sm font-mono text-gray-300 mt-1">{{ $lastHit ?? '—' }}</p>
            </div>
        </div>

        {{-- Scan types + Activity --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">

            {{-- Scan type breakdown --}}
            <div class="bg-gray-900 border border-gray-800 rounded-lg">
                <div class="px-5 py-4 border-b border-gray-800">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Scan Type Breakdown</p>
                </div>
                @php $maxScan = $scanTypes[0]['count'] ?? 1; @endphp
                <div class="divide-y divide-gray-800">
                    @foreach($scanTypes as $row)
                        <div class="px-5 py-2.5">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs font-mono px-1.5 py-0.5 rounded
                                    @switch($row['scan_type'])
                                        @case('env_probe') bg-red-900/30 text-red-400 @break
                                        @case('wp_admin') bg-blue-900/30 text-blue-400 @break
                                        @case('git_exposure') bg-purple-900/30 text-purple-400 @break
                                        @case('log4shell') bg-orange-900/30 text-orange-400 @break
                                        @case('spring_boot') bg-green-900/30 text-green-400 @break
                                        @case('phpmyadmin') bg-yellow-900/30 text-yellow-400 @break
                                        @default bg-gray-800 text-gray-500
                                    @endswitch
                                ">{{ $row['scan_type'] }}</span>
                                <span class="text-xs font-mono text-amber-400">{{ number_format($row['count']) }}</span>
                            </div>
                            <div class="h-1 bg-gray-800 rounded-full overflow-hidden">
                                <div class="h-full bg-amber-500/50 rounded-full" style="width: {{ round($row['count'] / $maxScan * 100) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Activity by hour --}}
            <div class="bg-gray-900 border border-gray-800 rounded-lg p-5">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-4">Activity by Hour of Day</p>
                <div id="vhostActivityData" data-activity="{{ json_encode($activityByHour) }}"></div>
                <div class="h-40" wire:ignore>
                    <canvas id="vhostActivityChart"></canvas>
                </div>
            </div>

        </div>

        {{-- Top paths + Top IPs --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">

            {{-- Top paths --}}
            <div class="bg-gray-900 border border-gray-800 rounded-lg">
                <div class="px-5 py-4 border-b border-gray-800">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Top Paths</p>
                </div>
                <div class="divide-y divide-gray-800">
                    @foreach($topPaths as $i => $row)
                        <div class="px-5 py-2.5 flex items-center gap-3">
                            <span class="text-xs font-mono text-gray-600 w-5 text-right flex-shrink-0">{{ $i + 1 }}</span>
                            <p class="text-xs font-mono text-gray-400 truncate flex-1 min-w-0">{{ $row['path'] }}</p>
                            @if($row['scan_type'])
                                <span class="text-xs font-mono px-1.5 py-0.5 rounded flex-shrink-0
                                    @switch($row['scan_type'])
                                        @case('env_probe') bg-red-900/30 text-red-400 @break
                                        @case('wp_admin') bg-blue-900/30 text-blue-400 @break
                                        @case('git_exposure') bg-purple-900/30 text-purple-400 @break
                                        @case('log4shell') bg-orange-900/30 text-orange-400 @break
                                        @case('spring_boot') bg-green-900/30 text-green-400 @break
                                        @case('phpmyadmin') bg-yellow-900/30 text-yellow-400 @break
                                        @default bg-gray-800 text-gray-500
                                    @endswitch
                                ">{{ $row['scan_type'] }}</span>
                            @endif
                            <span class="text-xs font-mono text-amber-400 flex-shrink-0">{{ number_format($row['count']) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Top attacking IPs --}}
            <div class="bg-gray-900 border border-gray-800 rounded-lg">
                <div class="px-5 py-4 border-b border-gray-800">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Top Attacking IPs</p>
                </div>
                <div class="divide-y divide-gray-800">
                    @foreach($topIps as $i => $entry)
                        <div class="px-5 py-2.5 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="text-xs font-mono text-gray-600 w-5 text-right flex-shrink-0">{{ $i + 1 }}</span>
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
                            <span class="text-xs font-mono text-amber-400 flex-shrink-0">{{ number_format($entry['count']) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

        </div>

    @endif
</div>

@script
<script>
    const vhostActivityEl = document.getElementById('vhostActivityChart');
    if (vhostActivityEl) {
        const data = JSON.parse(document.getElementById('vhostActivityData').dataset.activity || '[]');
        new Chart(vhostActivityEl, {
            type: 'bar',
            data: {
                labels: Array.from({ length: 24 }, (_, i) => String(i).padStart(2, '0')),
                datasets: [{ data, backgroundColor: 'rgba(251,191,36,0.6)', borderRadius: 2 }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
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
                    x: { ticks: { color: '#9ca3af', font: { size: 11, family: 'monospace' } }, grid: { color: 'rgba(75,85,99,0.15)' } },
                    y: { ticks: { color: '#9ca3af', font: { size: 11 }, maxTicksLimit: 4 }, grid: { color: 'rgba(75,85,99,0.15)' } },
                },
            },
        });
    }
</script>
@endscript
