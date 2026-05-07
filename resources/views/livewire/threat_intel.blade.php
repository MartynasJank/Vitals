<div>
    <div class="flex flex-wrap items-center justify-between gap-2 mb-6">
        <h1 class="text-xl font-bold text-gray-100">Threat Intelligence</h1>
        <div class="flex gap-1">
            @foreach(['24h' => '24h', '7d' => '7d', '30d' => '30d'] as $value => $label)
                <button wire:click="setRange('{{ $value }}')"
                        class="px-3 py-1 text-xs font-mono rounded transition-colors {{ $timeRange === $value ? 'bg-gray-700 text-gray-100' : 'text-gray-500 hover:text-gray-300' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- Chart data element — updated by Livewire on each render --}}
    <div id="threatChartData"
         data-volume="{{ json_encode($attackVolume) }}"
         data-heatmap="{{ json_encode($attackHeatmap) }}"
         data-countries="{{ json_encode($topCountries) }}"
         data-isps="{{ json_encode($topIsps) }}"
         data-orgs="{{ json_encode($topOrgs) }}"
         data-origins="{{ json_encode($attackOrigins) }}">
    </div>

    {{-- Attack volume --}}
    <div class="bg-gray-900 border border-gray-800 rounded-lg p-5 mb-4">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-4">Attack Volume</p>
        @if(empty($attackVolume))
            <p class="text-sm text-gray-600 font-mono">No data yet — run <span class="text-gray-500">vitals:enrich-ip</span> or wait for Fail2ban events</p>
        @else
            <div class="h-40" wire:ignore>
                <canvas id="volumeChart"></canvas>
            </div>
            <div class="flex items-center gap-4 mt-3">
                <span class="flex items-center gap-1.5 text-xs text-gray-500"><span class="w-3 h-0.5 bg-red-400 inline-block"></span>SSH</span>
                <span class="flex items-center gap-1.5 text-xs text-gray-500"><span class="w-3 h-0.5 bg-amber-400 inline-block"></span>Nginx</span>
            </div>
        @endif
    </div>

    {{-- Heatmap + Stat cards row --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
        <div class="lg:col-span-2 bg-gray-900 border border-gray-800 rounded-lg p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-4">Attacks by Hour of Day</p>
            @if(array_sum($attackHeatmap) === 0)
                <p class="text-sm text-gray-600 font-mono">No data yet</p>
            @else
                <div class="h-28" wire:ignore>
                    <canvas id="heatmapChart"></canvas>
                </div>
            @endif
        </div>

        <div class="space-y-4">
            <div class="bg-gray-900 border border-gray-800 rounded-lg p-5">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">Repeat Offender Rate</p>
                <p class="text-4xl font-bold {{ $repeatOffenderRate > 50 ? 'text-red-400' : ($repeatOffenderRate > 20 ? 'text-amber-400' : 'text-gray-100') }}">
                    {{ number_format($repeatOffenderRate, 1) }}<span class="text-xl text-gray-500 ml-1">%</span>
                </p>
                <p class="text-xs text-gray-600 mt-1">of IPs seen more than once</p>
            </div>

            <div class="bg-gray-900 border border-gray-800 rounded-lg p-5">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">Cross-Source IPs</p>
                <p class="text-4xl font-bold {{ count($crossSourceIps) > 10 ? 'text-red-400' : (count($crossSourceIps) > 0 ? 'text-amber-400' : 'text-gray-100') }}">
                    {{ count($crossSourceIps) }}
                </p>
                <p class="text-xs text-gray-600 mt-1">attacking both SSH & Nginx</p>
            </div>
        </div>
    </div>

    {{-- Attack origin map --}}
    <div class="bg-gray-900 border border-gray-800 rounded-lg mb-4 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-800">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Attack Origin Map</p>
        </div>
        @if(empty($attackOrigins))
            <div class="p-5">
                <p class="text-sm text-gray-600 font-mono">No geo data yet — IPs will be plotted as they are enriched</p>
            </div>
        @else
            <div id="originsMap" class="h-[500px]" wire:ignore></div>
        @endif
    </div>

    {{-- Countries + ISPs + Orgs --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-4">Top Source Countries</p>
            @if(empty($topCountries))
                <p class="text-sm text-gray-600 font-mono">No data yet</p>
            @else
                <div class="h-72" wire:ignore>
                    <canvas id="countriesChart"></canvas>
                </div>
            @endif
        </div>

        <div class="bg-gray-900 border border-gray-800 rounded-lg p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-4">Top ISPs</p>
            @if(empty($topIsps))
                <p class="text-sm text-gray-600 font-mono">No data yet</p>
            @else
                <div class="h-72" wire:ignore>
                    <canvas id="ispsChart"></canvas>
                </div>
            @endif
        </div>

        <div class="bg-gray-900 border border-gray-800 rounded-lg p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-4">Top Orgs</p>
            @if(empty($topOrgs))
                <p class="text-sm text-gray-600 font-mono">No data yet</p>
            @else
                <div class="h-72" wire:ignore>
                    <canvas id="orgsChart"></canvas>
                </div>
            @endif
        </div>
    </div>

    {{-- Tables row --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
        {{-- Top SSH usernames --}}
        <div class="bg-gray-900 border border-gray-800 rounded-lg">
            <div class="px-5 py-4 border-b border-gray-800">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Top Targeted SSH Usernames</p>
            </div>
            @if(empty($topUsernames))
                <div class="p-5">
                    <p class="text-sm text-gray-600 font-mono">No data yet</p>
                </div>
            @else
                <div class="divide-y divide-gray-800">
                    @foreach($topUsernames as $i => $row)
                        <div class="px-5 py-2.5 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="text-xs font-mono text-gray-600 w-5 text-right">{{ $i + 1 }}</span>
                                <p class="text-sm font-mono text-gray-300">{{ $row['username'] }}</p>
                            </div>
                            <span class="text-xs font-mono text-red-400">{{ number_format($row['count']) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Top Nginx paths --}}
        <div class="bg-gray-900 border border-gray-800 rounded-lg">
            <div class="px-5 py-4 border-b border-gray-800">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Top Scanned Nginx Paths</p>
            </div>
            @if(empty($topPaths))
                <div class="p-5">
                    <p class="text-sm text-gray-600 font-mono">No data yet</p>
                </div>
            @else
                <div class="divide-y divide-gray-800">
                    @foreach($topPaths as $i => $row)
                        <div class="px-5 py-2.5 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="text-xs font-mono text-gray-600 w-5 text-right flex-shrink-0">{{ $i + 1 }}</span>
                                <p class="text-xs font-mono text-gray-400 truncate">{{ $row['path'] }}</p>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <span class="text-xs font-mono px-1.5 py-0.5 rounded text-gray-400
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
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Top scanner referers --}}
        <div class="bg-gray-900 border border-gray-800 rounded-lg">
            <div class="px-5 py-4 border-b border-gray-800">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Top Scanner Referers</p>
            </div>
            @if(empty($topReferers))
                <div class="p-5">
                    <p class="text-sm text-gray-600 font-mono">No data yet</p>
                </div>
            @else
                <div class="divide-y divide-gray-800">
                    @foreach($topReferers as $i => $row)
                        <div class="px-5 py-2.5 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="text-xs font-mono text-gray-600 w-5 text-right flex-shrink-0">{{ $i + 1 }}</span>
                                <p class="text-xs font-mono text-gray-400 truncate">{{ $row['referer'] }}</p>
                            </div>
                            <span class="text-xs font-mono text-amber-400 flex-shrink-0">{{ number_format($row['count']) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Cross-source IPs --}}
    @if(!empty($crossSourceIps))
        <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-3">Cross-Source IPs (SSH + Nginx)</h2>
        <div class="bg-gray-900 border border-amber-900/40 rounded-lg divide-y divide-gray-800 mb-4">
            @foreach($crossSourceIps as $entry)
                <div class="px-5 py-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-sm font-mono text-amber-400">{{ $entry['ip'] }}</p>

                        @if($entry['country_code'])
                            <img src="https://flagcdn.com/16x12/{{ $entry['country_code'] }}.png"
                                 alt="{{ $entry['country'] ?? '' }}"
                                 class="w-4 h-3 object-cover rounded-sm opacity-80">
                        @endif

                        @if($entry['country'])
                            <span class="text-xs text-gray-500">{{ $entry['country'] }}</span>
                        @endif

                        @if($entry['isp'])
                            <span class="text-xs text-gray-600 font-mono">{{ $entry['isp'] }}</span>
                        @endif

                        <span class="text-xs px-1.5 py-0.5 rounded bg-red-900/30 text-red-400 font-mono">SSH: {{ $entry['ssh_count'] }}</span>
                        <span class="text-xs px-1.5 py-0.5 rounded bg-amber-900/30 text-amber-400 font-mono">Nginx: {{ $entry['nginx_count'] }}</span>
                        <span class="text-xs text-gray-600 font-mono">{{ $entry['total_hits'] }}× total</span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

@script
<script>
    const chartOpts = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { ticks: { color: '#9ca3af', font: { size: 12, family: 'monospace' }, maxTicksLimit: 12, maxRotation: 0 }, grid: { color: 'rgba(75,85,99,0.15)' } },
            y: { ticks: { color: '#9ca3af', font: { size: 12 }, maxTicksLimit: 5 }, grid: { color: 'rgba(75,85,99,0.15)' } },
        },
    };

    const barOpts = (horizontal) => ({
        ...chartOpts,
        indexAxis: horizontal ? 'y' : 'x',
        plugins: { legend: { display: false } },
        scales: {
            x: { ticks: { color: '#9ca3af', font: { size: 12 } }, grid: { color: 'rgba(75,85,99,0.15)' } },
            y: { ticks: { color: '#9ca3af', font: { size: 12, family: 'monospace' }, maxTicksLimit: 10 }, grid: { color: 'rgba(75,85,99,0.15)' } },
        },
    });

    const readData = () => {
        const el = document.getElementById('threatChartData');
        return {
            volume: JSON.parse(el.dataset.volume || '[]'),
            heatmap: JSON.parse(el.dataset.heatmap || '[]'),
            countries: JSON.parse(el.dataset.countries || '[]'),
            isps: JSON.parse(el.dataset.isps || '[]'),
            orgs: JSON.parse(el.dataset.orgs || '[]'),
            origins: JSON.parse(el.dataset.origins || '[]'),
        };
    };

    const d = readData();

    const volumeEl = document.getElementById('volumeChart');
    const volumeChart = volumeEl ? new Chart(volumeEl, {
        type: 'line',
        data: {
            labels: d.volume.map(r => r.label),
            datasets: [
                { label: 'SSH', data: d.volume.map(r => r.ssh), borderColor: '#f87171', backgroundColor: 'rgba(248,113,113,0.08)', fill: true, tension: 0.4, pointRadius: 0, borderWidth: 1.5 },
                { label: 'Nginx', data: d.volume.map(r => r.nginx), borderColor: '#fbbf24', backgroundColor: 'rgba(251,191,36,0.08)', fill: true, tension: 0.4, pointRadius: 0, borderWidth: 1.5 },
            ],
        },
        options: { ...chartOpts, plugins: { legend: { display: false } } },
    }) : null;

    const heatmapEl = document.getElementById('heatmapChart');
    const heatmapChart = heatmapEl ? new Chart(heatmapEl, {
        type: 'bar',
        data: {
            labels: Array.from({ length: 24 }, (_, i) => String(i).padStart(2, '0')),
            datasets: [{ data: d.heatmap, backgroundColor: 'rgba(248,113,113,0.6)', borderRadius: 2 }],
        },
        options: barOpts(false),
    }) : null;

    const countriesEl = document.getElementById('countriesChart');
    const countriesChart = countriesEl ? new Chart(countriesEl, {
        type: 'bar',
        data: {
            labels: d.countries.map(r => r.country),
            datasets: [{ data: d.countries.map(r => r.count), backgroundColor: 'rgba(96,165,250,0.7)', borderRadius: 2 }],
        },
        options: barOpts(true),
    }) : null;

    const orgsEl = document.getElementById('orgsChart');
    const orgsChart = orgsEl ? new Chart(orgsEl, {
        type: 'bar',
        data: {
            labels: d.orgs.map(r => r.org),
            datasets: [{ data: d.orgs.map(r => r.count), backgroundColor: 'rgba(52,211,153,0.7)', borderRadius: 2 }],
        },
        options: barOpts(true),
    }) : null;

    const ispsEl = document.getElementById('ispsChart');
    const ispsChart = ispsEl ? new Chart(ispsEl, {
        type: 'bar',
        data: {
            labels: d.isps.map(r => r.isp),
            datasets: [{ data: d.isps.map(r => r.count), backgroundColor: 'rgba(167,139,250,0.7)', borderRadius: 2 }],
        },
        options: barOpts(true),
    }) : null;

    const originsMapEl = document.getElementById('originsMap');
    let attackMap = null;
    let markersLayer = null;

    const plotOrigins = (origins) => {
        if (! markersLayer) { return; }
        markersLayer.clearLayers();
        origins.forEach(r => {
            const radius = Math.min(3 + Math.log2(r.count + 1) * 2, 14);
            L.circleMarker([r.lat, r.lon], {
                radius,
                fillColor: '#f87171',
                color: 'transparent',
                fillOpacity: 0.65,
            }).bindTooltip(`${r.count} attack${r.count !== 1 ? 's' : ''} · ${r.lat.toFixed(1)}°, ${r.lon.toFixed(1)}°`, { className: 'leaflet-dark-tooltip' })
              .addTo(markersLayer);
        });
    };

    if (originsMapEl) {
        attackMap = L.map(originsMapEl, { zoomControl: false, attributionControl: false, scrollWheelZoom: false }).setView([20, 0], 2);
        L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_nolabels/{z}/{x}/{y}{r}.png', { maxZoom: 19 }).addTo(attackMap);
        markersLayer = L.layerGroup().addTo(attackMap);
        plotOrigins(d.origins);
    }

    new MutationObserver(() => {
        const updated = readData();

        if (volumeChart) {
            volumeChart.data.labels = updated.volume.map(r => r.label);
            volumeChart.data.datasets[0].data = updated.volume.map(r => r.ssh);
            volumeChart.data.datasets[1].data = updated.volume.map(r => r.nginx);
            volumeChart.update('none');
        }

        if (heatmapChart) {
            heatmapChart.data.datasets[0].data = updated.heatmap;
            heatmapChart.update('none');
        }

        if (countriesChart) {
            countriesChart.data.labels = updated.countries.map(r => r.country);
            countriesChart.data.datasets[0].data = updated.countries.map(r => r.count);
            countriesChart.update('none');
        }

        if (orgsChart) {
            orgsChart.data.labels = updated.orgs.map(r => r.org);
            orgsChart.data.datasets[0].data = updated.orgs.map(r => r.count);
            orgsChart.update('none');
        }

        if (ispsChart) {
            ispsChart.data.labels = updated.isps.map(r => r.isp);
            ispsChart.data.datasets[0].data = updated.isps.map(r => r.count);
            ispsChart.update('none');
        }

        if (attackMap) {
            plotOrigins(updated.origins);
        }
    }).observe(document.getElementById('threatChartData'), { attributes: true });
</script>
<style>
    .leaflet-dark-tooltip { background: #1f2937; border: 1px solid #374151; color: #d1d5db; font-family: monospace; font-size: 11px; box-shadow: none; }
    .leaflet-dark-tooltip::before { display: none; }
</style>
@endscript
