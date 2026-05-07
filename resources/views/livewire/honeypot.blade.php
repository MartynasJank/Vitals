<div>
    @if($banMessage)
        <div class="mb-4 px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-300">
            {{ $banMessage }}
        </div>
    @endif

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold text-gray-100">Honeypot</h1>
        <span class="flex items-center gap-1.5 text-xs font-mono text-green-400">
            <span class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></span>
            live
        </span>
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">Total Sessions</p>
            <p class="text-3xl font-bold text-gray-100">{{ number_format($stats['total_sessions']) }}</p>
        </div>
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">Unique Attackers</p>
            <p class="text-3xl font-bold text-amber-400">{{ number_format($stats['unique_ips']) }}</p>
        </div>
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">Commands Captured</p>
            <p class="text-3xl font-bold text-blue-400">{{ number_format($stats['total_commands']) }}</p>
        </div>
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">Malware Downloads</p>
            <p class="text-3xl font-bold text-red-400">{{ number_format($stats['total_downloads']) }}</p>
        </div>
    </div>

    {{-- Recent sessions --}}
    <div class="bg-gray-900 border border-gray-800 rounded-lg mb-4">
        <div class="px-5 py-4 border-b border-gray-800 flex items-center justify-between">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Recent Attacker Sessions</p>
            <button wire:click="$toggle('loginsOnly')"
                    class="text-xs font-mono px-2.5 py-1 rounded transition-colors {{ $loginsOnly ? 'bg-green-500/20 text-green-400 border border-green-500/30' : 'bg-gray-800 text-gray-500 border border-gray-700 hover:text-gray-300' }}">
                logins only
            </button>
        </div>
        @if(empty($recentSessions))
            <div class="p-5">
                <p class="text-sm text-gray-600 font-mono">{{ $loginsOnly ? 'No login attempts found' : 'No sessions yet — attackers will appear here once they connect' }}</p>
            </div>
        @else
            <div class="divide-y divide-gray-800">
                @foreach($recentSessions as $session)
                    <div x-data="{ open: false }" class="px-5 py-3">
                        <div class="flex items-start justify-between gap-2">
                        <div class="flex flex-wrap items-center gap-2 cursor-pointer" @click="open = !open">
                            <span class="text-xs font-mono text-gray-600">{{ $session['started_at'] }}</span>

                            @if($session['country_code'])
                                <img src="https://flagcdn.com/16x12/{{ $session['country_code'] }}.png"
                                     alt="{{ $session['country'] ?? '' }}"
                                     class="w-4 h-3 object-cover rounded-sm opacity-80">
                            @endif

                            <span class="text-sm font-mono text-amber-400">{{ $session['ip'] ?? '—' }}</span>

                            @if($session['country'])
                                <span class="text-xs text-gray-500">{{ $session['country'] }}</span>
                            @endif

                            @if($session['isp'])
                                <span class="text-xs text-gray-600 font-mono">{{ $session['isp'] }}</span>
                            @endif

                            @if($session['username'])
                                <span class="text-xs px-1.5 py-0.5 rounded bg-green-900/30 text-green-400 font-mono">{{ $session['username'] }}/{{ $session['password'] }}</span>
                            @else
                                <span class="text-xs px-1.5 py-0.5 rounded bg-gray-800 text-gray-600 font-mono">scan only</span>
                            @endif

                            @if($session['duration_seconds'])
                                <span class="text-xs text-gray-600 font-mono">{{ number_format($session['duration_seconds'], 1) }}s</span>
                            @endif

                            @if(!empty($session['commands']))
                                <span class="text-xs px-1.5 py-0.5 rounded bg-blue-900/30 text-blue-400 font-mono">{{ count($session['commands']) }} cmd{{ count($session['commands']) !== 1 ? 's' : '' }}</span>
                            @endif

                            @if(!empty($session['commands']))
                                <svg class="w-3 h-3 text-gray-600 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            @endif
                        </div>
                        <button wire:click="ban('{{ $session['ip'] }}')"
                                wire:confirm="Ban {{ $session['ip'] }}?"
                                class="text-xs text-gray-600 hover:text-red-400 transition-colors font-mono flex-shrink-0 mt-0.5">
                            ban
                        </button>
                        </div>

                        @if(!empty($session['commands']))
                            <div x-show="open" x-cloak class="mt-3 pl-4 border-l border-gray-800 space-y-1">
                                @foreach($session['commands'] as $cmd)
                                    <p class="text-xs font-mono text-green-300">$ {{ $cmd }}</p>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Credentials + Commands --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
        {{-- Top credentials --}}
        <div class="bg-gray-900 border border-gray-800 rounded-lg">
            <div class="px-5 py-4 border-b border-gray-800">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Top Credentials Tried</p>
            </div>
            @if(empty($topCredentials))
                <div class="p-5">
                    <p class="text-sm text-gray-600 font-mono">No data yet</p>
                </div>
            @else
                <div class="divide-y divide-gray-800">
                    @foreach($topCredentials as $i => $cred)
                        <div class="px-5 py-2.5 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="text-xs font-mono text-gray-600 w-5 text-right flex-shrink-0">{{ $i + 1 }}</span>
                                <span class="text-xs font-mono text-green-400 flex-shrink-0">{{ $cred['username'] }}</span>
                                <span class="text-xs text-gray-700">/</span>
                                <span class="text-xs font-mono text-gray-400 truncate">{{ $cred['password'] }}</span>
                            </div>
                            <span class="text-xs font-mono text-amber-400 flex-shrink-0">{{ number_format($cred['hit_count']) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Top commands --}}
        <div class="bg-gray-900 border border-gray-800 rounded-lg">
            <div class="px-5 py-4 border-b border-gray-800">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Top Commands Run</p>
            </div>
            @if(empty($topCommands))
                <div class="p-5">
                    <p class="text-sm text-gray-600 font-mono">No data yet</p>
                </div>
            @else
                <div class="divide-y divide-gray-800">
                    @foreach($topCommands as $i => $cmd)
                        <div class="px-5 py-2.5 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="text-xs font-mono text-gray-600 w-5 text-right flex-shrink-0">{{ $i + 1 }}</span>
                                <span class="text-xs font-mono text-green-300 truncate">$ {{ $cmd['input'] }}</span>
                            </div>
                            <span class="text-xs font-mono text-blue-400 flex-shrink-0">{{ number_format($cmd['count']) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Malware downloads --}}
    @if(!empty($topDownloads))
        <div class="bg-gray-900 border border-red-900/40 rounded-lg">
            <div class="px-5 py-4 border-b border-red-900/40">
                <p class="text-xs font-medium text-red-500 uppercase tracking-wider">Malware Download URLs</p>
            </div>
            <div class="divide-y divide-gray-800">
                @foreach($topDownloads as $i => $dl)
                    <div class="px-5 py-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-mono text-red-400 break-all">{{ $dl['url'] }}</p>
                                <div class="flex items-center gap-3 mt-1">
                                    @if($dl['filename'])
                                        <span class="text-xs text-gray-500 font-mono">{{ $dl['filename'] }}</span>
                                    @endif
                                    @if($dl['file_hash'])
                                        <span class="text-xs text-gray-700 font-mono">{{ substr($dl['file_hash'], 0, 16) }}…</span>
                                    @endif
                                </div>
                            </div>
                            <span class="text-xs font-mono text-red-400 flex-shrink-0">{{ number_format($dl['count']) }}×</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
