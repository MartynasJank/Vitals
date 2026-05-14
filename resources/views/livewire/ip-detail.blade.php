<div>
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ url()->previous(route('threat-intel')) }}"
           class="text-gray-600 hover:text-gray-300 transition-colors font-mono text-sm">← back</a>
        <h1 class="text-xl font-bold text-gray-100 font-mono">{{ $ip }}</h1>
    </div>

    @if(!$profile)
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-8 text-center">
            <p class="text-gray-500 font-mono text-sm">IP not found in threat database.</p>
        </div>
    @else

        {{-- Header badges --}}
        <div class="flex flex-wrap items-center gap-2 mb-6">
            @if($profile->country_code)
                <img src="https://flagcdn.com/16x12/{{ strtolower($profile->country_code) }}.png"
                     alt="{{ $profile->country }}"
                     class="w-4 h-3 object-cover rounded-sm opacity-80">
            @endif
            @if($profile->country)
                <span class="text-sm text-gray-400">{{ $profile->country }}</span>
            @endif
            @if($profile->city)
                <span class="text-sm text-gray-600">{{ $profile->city }}</span>
            @endif
            @if($profile->is_vpn)
                <span class="text-xs px-2 py-0.5 rounded-full bg-purple-900/40 text-purple-400 font-mono">VPN</span>
            @endif
            @if($profile->is_proxy)
                <span class="text-xs px-2 py-0.5 rounded-full bg-blue-900/40 text-blue-400 font-mono">proxy</span>
            @endif
            @if($profile->is_tor)
                <span class="text-xs px-2 py-0.5 rounded-full bg-amber-900/40 text-amber-400 font-mono">Tor</span>
            @endif
        </div>

        {{-- Stat cards --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
            <div class="bg-gray-900 border border-gray-800 rounded-lg p-4">
                <p class="text-xs text-gray-500 mb-1">Total Hits</p>
                <p class="text-2xl font-bold text-red-400 font-mono">{{ number_format($profile->total_hits) }}</p>
            </div>
            <div class="bg-gray-900 border border-gray-800 rounded-lg p-4">
                <p class="text-xs text-gray-500 mb-1">SSH</p>
                <p class="text-2xl font-bold text-gray-100 font-mono">{{ number_format($sshCount) }}</p>
            </div>
            <div class="bg-gray-900 border border-gray-800 rounded-lg p-4">
                <p class="text-xs text-gray-500 mb-1">Nginx</p>
                <p class="text-2xl font-bold text-gray-100 font-mono">{{ number_format($nginxCount) }}</p>
            </div>
            <div class="bg-gray-900 border border-gray-800 rounded-lg p-4">
                <p class="text-xs text-gray-500 mb-1">Honeypot</p>
                <p class="text-2xl font-bold text-gray-100 font-mono">{{ number_format($cowrieCount) }}</p>
            </div>
            <div class="bg-gray-900 border border-gray-800 rounded-lg p-4">
                <p class="text-xs text-gray-500 mb-1">Malware Files</p>
                <p class="text-2xl font-bold {{ $malwareCount > 0 ? 'text-red-400' : 'text-gray-100' }} font-mono">{{ number_format($malwareCount) }}</p>
            </div>
            <div class="bg-gray-900 border border-gray-800 rounded-lg p-4">
                <p class="text-xs text-gray-500 mb-1">First / Last</p>
                <p class="text-xs font-mono text-gray-300 mt-1">{{ $profile->first_seen?->format('Y-m-d') ?? '—' }}</p>
                <p class="text-xs font-mono text-gray-500">{{ $profile->last_seen?->format('Y-m-d') ?? '—' }}</p>
            </div>
        </div>

        {{-- Info panel --}}
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-5 mb-6">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-x-8 gap-y-4">
                <div>
                    <p class="text-xs text-gray-500 mb-1">ISP</p>
                    <p class="text-sm font-mono text-gray-300">{{ $profile->isp ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 mb-1">ASN</p>
                    <p class="text-sm font-mono text-gray-300">{{ $profile->asn ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 mb-1">Org</p>
                    <p class="text-sm font-mono text-gray-300">{{ $profile->org ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 mb-1">City</p>
                    <p class="text-sm font-mono text-gray-300">{{ $profile->city ?? '—' }}</p>
                </div>
            </div>
        </div>

        {{-- Aggregated stats --}}
        @if(!empty($topSshUsernames) || !empty($nginxScanTypes) || !empty($topNginxPaths) || array_sum($activityByHour) > 0)
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">

                {{-- Top SSH usernames --}}
                @if(!empty($topSshUsernames))
                    <div class="bg-gray-900 border border-gray-800 rounded-lg">
                        <div class="px-5 py-4 border-b border-gray-800">
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Top SSH Usernames Tried</p>
                        </div>
                        @php $maxSsh = $topSshUsernames[0]['count'] ?? 1; @endphp
                        <div class="divide-y divide-gray-800">
                            @foreach($topSshUsernames as $row)
                                <div class="px-5 py-2.5">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-sm font-mono text-gray-300">{{ $row['username'] }}</span>
                                        <span class="text-xs font-mono text-red-400">{{ number_format($row['count']) }}</span>
                                    </div>
                                    <div class="h-1 bg-gray-800 rounded-full overflow-hidden">
                                        <div class="h-full bg-red-500/50 rounded-full" style="width: {{ round($row['count'] / $maxSsh * 100) }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Activity by hour --}}
                @if(array_sum($activityByHour) > 0)
                    <div class="bg-gray-900 border border-gray-800 rounded-lg p-5">
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-4">Activity by Hour of Day</p>
                        <div id="ipActivityData" data-activity="{{ json_encode($activityByHour) }}"></div>
                        <div class="h-28" wire:ignore>
                            <canvas id="ipActivityChart"></canvas>
                        </div>
                    </div>
                @endif

                {{-- Nginx scan type breakdown --}}
                @if(!empty($nginxScanTypes))
                    <div class="bg-gray-900 border border-gray-800 rounded-lg">
                        <div class="px-5 py-4 border-b border-gray-800">
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Nginx Scan Type Breakdown</p>
                        </div>
                        @php $maxNginx = $nginxScanTypes[0]['count'] ?? 1; @endphp
                        <div class="divide-y divide-gray-800">
                            @foreach($nginxScanTypes as $row)
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
                                        <div class="h-full bg-amber-500/50 rounded-full" style="width: {{ round($row['count'] / $maxNginx * 100) }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Top Nginx paths --}}
                @if(!empty($topNginxPaths))
                    <div class="bg-gray-900 border border-gray-800 rounded-lg">
                        <div class="px-5 py-4 border-b border-gray-800">
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Top Nginx Paths</p>
                        </div>
                        <div class="divide-y divide-gray-800">
                            @foreach($topNginxPaths as $i => $row)
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
                @endif

            </div>
        @endif

        {{-- Malware files --}}
        @if($malwareCount > 0)
            <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-3">Malware Files Dropped</h2>
            <div class="bg-gray-900 border border-red-900/40 rounded-lg divide-y divide-gray-800 mb-6">
                @foreach($malwareFiles as $file)
                    @php
                        $hasDanger = $file->highlights->whereIn('category', ['wallet', 'credential', 'lateral', 'onion'])->isNotEmpty();
                        $hasWarn = !$hasDanger && $file->highlights->whereIn('category', ['discord', 'telegram', 'mining', 'self_delete', 'anti_vm', 'persistence'])->isNotEmpty();
                        $entropyColor = $file->entropy >= 7.5 ? 'text-red-400' : ($file->entropy >= 5.5 ? 'text-amber-400' : 'text-green-400');
                    @endphp
                    <div class="px-5 py-4">
                        <div class="flex flex-wrap items-center gap-3 mb-2">
                            <a href="{{ route('honeypot.malware.detail', $file->sha256) }}"
                               class="text-xs font-mono text-gray-300 hover:text-gray-100 transition-colors">{{ substr($file->sha256, 0, 16) }}…</a>

                            @if($file->malware_family)
                                <span class="text-xs px-2 py-0.5 rounded bg-red-900/50 text-red-300 font-mono border border-red-800/50">{{ $file->malware_family }}</span>
                            @endif

                            @if($file->elf_arch)
                                <span class="text-xs px-2 py-0.5 rounded bg-gray-800 text-gray-400 font-mono">{{ $file->elf_arch }}{{ $file->elf_bits ? ' · '.$file->elf_bits.'bit' : '' }}</span>
                            @endif

                            @if($file->entropy !== null)
                                <span class="text-xs font-mono {{ $entropyColor }}">entropy {{ number_format($file->entropy, 1) }}</span>
                            @endif

                            @if($file->is_upx)
                                <span class="text-xs px-2 py-0.5 rounded bg-purple-900/40 text-purple-400 font-mono">UPX</span>
                            @endif

                            @if($file->vt_status === 'found')
                                <span class="text-xs font-mono {{ $file->vt_malicious > 0 ? 'text-red-400' : 'text-green-400' }}">
                                    VT: {{ $file->vt_malicious }}/{{ $file->vt_total }}
                                </span>
                            @elseif($file->vt_status === 'pending')
                                <span class="text-xs text-gray-600 font-mono">VT: pending</span>
                            @endif

                            <span class="text-xs text-gray-600 font-mono ml-auto">{{ number_format($file->file_size_bytes / 1024, 1) }} KB</span>
                        </div>

                        @if($file->highlights->isNotEmpty())
                            <div class="flex flex-wrap gap-1.5 mt-2">
                                @foreach($file->highlights->take(8) as $indicator)
                                    @php
                                        $color = match($indicator->category) {
                                            'wallet' => 'text-yellow-400 bg-yellow-900/20',
                                            'credential' => 'text-red-400 bg-red-900/20',
                                            'lateral' => 'text-orange-400 bg-orange-900/20',
                                            'onion' => 'text-purple-400 bg-purple-900/20',
                                            'mining' => 'text-red-400 bg-red-900/20',
                                            'discord', 'telegram' => 'text-sky-400 bg-sky-900/20',
                                            default => 'text-gray-400 bg-gray-800',
                                        };
                                    @endphp
                                    <span class="text-xs font-mono px-1.5 py-0.5 rounded {{ $color }}" title="{{ $indicator->category }}">{{ Str::limit($indicator->value, 40) }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Cowrie sessions --}}
        @if($cowrieCount > 0)
            <div x-data="{ onlyCmds: false }" class="mb-6">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider">Honeypot Sessions</h2>
                <button @click="onlyCmds = !onlyCmds"
                        :class="onlyCmds ? 'bg-gray-700 text-gray-200' : 'bg-gray-800 text-gray-500'"
                        class="text-xs font-mono px-2.5 py-1 rounded transition-colors">
                    has commands
                </button>
            </div>
            <div class="bg-gray-900 border border-gray-800 rounded-lg divide-y divide-gray-800">
                @foreach($cowrieSessions as $session)
                    <div x-data="{ open: false }" x-show="!onlyCmds || {{ count($session['commands']) > 0 ? 'true' : 'false' }}" class="px-5 py-3">
                        <div class="flex items-center justify-between gap-3 cursor-pointer" @click="open = !open">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-xs font-mono text-gray-600">{{ $session['started_at'] }}</span>
                                @if($session['username'])
                                    <span class="text-xs px-1.5 py-0.5 rounded font-mono {{ $session['is_success'] ? 'bg-green-900/30 text-green-400' : 'bg-gray-800 text-gray-500' }}">
                                        {{ $session['username'] }}/{{ $session['password'] }}
                                    </span>
                                @else
                                    <span class="text-xs px-1.5 py-0.5 rounded bg-gray-800 text-gray-600 font-mono">scan only</span>
                                @endif
                                @if($session['duration_seconds'])
                                    <span class="text-xs text-gray-600 font-mono">{{ number_format($session['duration_seconds'], 1) }}s</span>
                                @endif
                                @if($session['is_interesting'])
                                    <span class="text-xs px-1.5 py-0.5 rounded bg-red-900/30 text-red-400 font-mono">interesting</span>
                                @endif
                                @if(count($session['commands']) > 0)
                                    <span class="text-xs text-gray-600">{{ count($session['commands']) }} cmd{{ count($session['commands']) !== 1 ? 's' : '' }}</span>
                                @endif
                                @php
                                    $uploadCount = collect($session['downloads'])->where('type', 'upload')->count();
                                    $downloadCount = collect($session['downloads'])->where('type', 'download')->count();
                                @endphp
                                @if($uploadCount > 0)
                                    <span class="text-xs text-purple-500">{{ $uploadCount }} upload{{ $uploadCount !== 1 ? 's' : '' }}</span>
                                @endif
                                @if($downloadCount > 0)
                                    <span class="text-xs text-amber-600">{{ $downloadCount }} download{{ $downloadCount !== 1 ? 's' : '' }}</span>
                                @endif
                            </div>
                            <svg class="w-3.5 h-3.5 text-gray-600 flex-shrink-0 transition-transform" :class="{ 'rotate-90': open }"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </div>
                        <div x-show="open" x-cloak class="mt-3 space-y-2">
                            @foreach($session['commands'] as $cmd)
                                @php
                                    $highlighted = preg_replace(
                                        '/\b(\d{1,3}(?:\.\d{1,3}){3}(?:\/\d+)?|\d+)\b/',
                                        '<span class="text-red-400">$1</span>',
                                        e($cmd)
                                    );
                                @endphp
                                <p class="text-xs font-mono text-green-400 bg-gray-950 px-3 py-1.5 rounded">{!! '$ '.$highlighted !!}</p>
                            @endforeach
                            @foreach($session['downloads'] as $dl)
                                @if($dl['type'] === 'upload')
                                    <p class="text-xs font-mono text-purple-400 bg-gray-950 px-3 py-1.5 rounded truncate">↑ {{ $dl['label'] }}</p>
                                @else
                                    <p class="text-xs font-mono text-amber-400 bg-gray-950 px-3 py-1.5 rounded truncate">↓ {{ $dl['label'] }}</p>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
            </div>
        @endif

        {{-- SSH attempts --}}
        @if($sshCount > 0)
            <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-3">
                SSH Attempts <span class="text-gray-600 font-normal normal-case">(last 50 of {{ number_format($sshCount) }})</span>
            </h2>
            <div class="bg-gray-900 border border-gray-800 rounded-lg divide-y divide-gray-800 mb-6">
                @foreach($sshAttempts as $attempt)
                    <div class="px-5 py-2.5 flex items-center justify-between gap-4">
                        <p class="text-sm font-mono text-gray-300">{{ $attempt['username'] ?? '—' }}</p>
                        <p class="text-xs font-mono text-gray-600">{{ $attempt['timestamp'] }}</p>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Nginx hits --}}
        @if($nginxCount > 0)
            <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-3">
                Nginx Hits <span class="text-gray-600 font-normal normal-case">(last 50 of {{ number_format($nginxCount) }})</span>
            </h2>
            <div class="bg-gray-900 border border-gray-800 rounded-lg divide-y divide-gray-800 mb-6">
                @foreach($nginxHits as $hit)
                    <div class="px-5 py-2.5">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="text-xs font-mono text-gray-600 flex-shrink-0">{{ $hit['method'] }}</span>
                                <p class="text-sm font-mono text-gray-300 truncate">{{ $hit['path'] }}</p>
                                @if($hit['scan_type'])
                                    <span class="text-xs font-mono px-1.5 py-0.5 rounded bg-gray-800 text-gray-500 flex-shrink-0">{{ $hit['scan_type'] }}</span>
                                @endif
                            </div>
                            <p class="text-xs font-mono text-gray-600 flex-shrink-0">{{ $hit['timestamp'] }}</p>
                        </div>
                        @if($hit['user_agent'])
                            <p class="text-xs font-mono text-gray-600 truncate mt-1">{{ $hit['user_agent'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

    @endif
</div>

@script
<script>
    const activityEl = document.getElementById('ipActivityChart');
    if (activityEl) {
        const data = JSON.parse(document.getElementById('ipActivityData').dataset.activity || '[]');
        new Chart(activityEl, {
            type: 'bar',
            data: {
                labels: Array.from({ length: 24 }, (_, i) => String(i).padStart(2, '0')),
                datasets: [{ data, backgroundColor: 'rgba(248,113,113,0.6)', borderRadius: 2 }],
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
