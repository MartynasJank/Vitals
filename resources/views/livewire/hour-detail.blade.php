<div>
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('threat-intel') }}"
           class="text-gray-600 hover:text-gray-300 transition-colors font-mono text-sm">← back</a>
        <h1 class="text-xl font-bold text-gray-100 font-mono">{{ $label }}</h1>
    </div>

    {{-- Summary cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-4">
            <p class="text-xs text-gray-500 mb-1">SSH Attempts</p>
            <p class="text-2xl font-bold text-red-400 font-mono">{{ number_format($sshCount) }}</p>
        </div>
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-4">
            <p class="text-xs text-gray-500 mb-1">Nginx Hits</p>
            <p class="text-2xl font-bold text-amber-400 font-mono">{{ number_format($nginxCount) }}</p>
        </div>
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-4">
            <p class="text-xs text-gray-500 mb-1">Honeypot Sessions</p>
            <p class="text-2xl font-bold text-blue-400 font-mono">{{ number_format($cowrieCount) }}</p>
        </div>
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-4">
            <p class="text-xs text-gray-500 mb-1">Unique IPs</p>
            <p class="text-2xl font-bold text-gray-100 font-mono">{{ number_format($uniqueIps) }}</p>
        </div>
    </div>

    @if($sshCount === 0 && $nginxCount === 0 && $cowrieCount === 0)
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-8 text-center">
            <p class="text-gray-500 font-mono text-sm">No activity recorded for this hour.</p>
        </div>
    @else

        {{-- SSH Attempts --}}
        @if(!empty($sshAttempts))
            <div class="bg-gray-900 border border-gray-800 rounded-lg mb-4">
                <div class="px-5 py-4 border-b border-gray-800 flex items-center justify-between">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">SSH Attempts</p>
                    <span class="text-xs font-mono text-red-400">{{ number_format($sshCount) }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs font-mono">
                        <thead>
                            <tr class="border-b border-gray-800">
                                <th class="px-5 py-2 text-left text-gray-600 font-normal">Time</th>
                                <th class="px-5 py-2 text-left text-gray-600 font-normal">IP</th>
                                <th class="px-5 py-2 text-left text-gray-600 font-normal">Country</th>
                                <th class="px-5 py-2 text-left text-gray-600 font-normal">Username</th>
                                <th class="px-5 py-2 text-left text-gray-600 font-normal hidden sm:table-cell">ISP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800/50">
                            @foreach($sshAttempts as $attempt)
                                <tr class="hover:bg-gray-800/30 transition-colors">
                                    <td class="px-5 py-2 text-gray-500">{{ $attempt['timestamp'] ?? '—' }}</td>
                                    <td class="px-5 py-2">
                                        <a href="{{ route('ip-detail', $attempt['ip']) }}"
                                           class="text-red-400 hover:text-red-300 hover:underline transition-colors">{{ $attempt['ip'] }}</a>
                                    </td>
                                    <td class="px-5 py-2">
                                        <div class="flex items-center gap-1.5">
                                            @if($attempt['country_code'])
                                                <img src="https://flagcdn.com/16x12/{{ $attempt['country_code'] }}.png"
                                                     alt="{{ $attempt['country'] ?? '' }}"
                                                     class="w-4 h-3 object-cover rounded-sm opacity-70">
                                            @endif
                                            <span class="text-gray-400">{{ $attempt['country'] ?? '—' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-2 text-gray-300">{{ $attempt['username'] ?? '—' }}</td>
                                    <td class="px-5 py-2 text-gray-600 hidden sm:table-cell">{{ $attempt['isp'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Nginx Hits --}}
        @if(!empty($nginxHits))
            <div class="bg-gray-900 border border-gray-800 rounded-lg mb-4">
                <div class="px-5 py-4 border-b border-gray-800 flex items-center justify-between">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Nginx Hits</p>
                    <span class="text-xs font-mono text-amber-400">{{ number_format($nginxCount) }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs font-mono">
                        <thead>
                            <tr class="border-b border-gray-800">
                                <th class="px-5 py-2 text-left text-gray-600 font-normal">Time</th>
                                <th class="px-5 py-2 text-left text-gray-600 font-normal">IP</th>
                                <th class="px-5 py-2 text-left text-gray-600 font-normal hidden sm:table-cell">Country</th>
                                <th class="px-5 py-2 text-left text-gray-600 font-normal">Method</th>
                                <th class="px-5 py-2 text-left text-gray-600 font-normal">Path</th>
                                <th class="px-5 py-2 text-left text-gray-600 font-normal hidden md:table-cell">Type</th>
                                <th class="px-5 py-2 text-left text-gray-600 font-normal hidden md:table-cell">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800/50">
                            @foreach($nginxHits as $hit)
                                <tr class="hover:bg-gray-800/30 transition-colors">
                                    <td class="px-5 py-2 text-gray-500">{{ $hit['timestamp'] ?? '—' }}</td>
                                    <td class="px-5 py-2">
                                        <a href="{{ route('ip-detail', $hit['ip']) }}"
                                           class="text-amber-400 hover:text-amber-300 hover:underline transition-colors">{{ $hit['ip'] }}</a>
                                    </td>
                                    <td class="px-5 py-2 hidden sm:table-cell">
                                        <div class="flex items-center gap-1.5">
                                            @if($hit['country_code'])
                                                <img src="https://flagcdn.com/16x12/{{ $hit['country_code'] }}.png"
                                                     alt="{{ $hit['country'] ?? '' }}"
                                                     class="w-4 h-3 object-cover rounded-sm opacity-70">
                                            @endif
                                            <span class="text-gray-400">{{ $hit['country'] ?? '—' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-2 text-gray-400">{{ $hit['method'] ?? '—' }}</td>
                                    <td class="px-5 py-2 text-gray-300 max-w-[160px] truncate">{{ $hit['path'] ?? '—' }}</td>
                                    <td class="px-5 py-2 hidden md:table-cell">
                                        @if($hit['scan_type'])
                                            <span class="px-1.5 py-0.5 rounded text-xs
                                                @switch($hit['scan_type'])
                                                    @case('env_probe') bg-red-900/30 text-red-400 @break
                                                    @case('wp_admin') bg-blue-900/30 text-blue-400 @break
                                                    @case('git_exposure') bg-purple-900/30 text-purple-400 @break
                                                    @case('log4shell') bg-orange-900/30 text-orange-400 @break
                                                    @case('spring_boot') bg-green-900/30 text-green-400 @break
                                                    @case('phpmyadmin') bg-yellow-900/30 text-yellow-400 @break
                                                    @default bg-gray-800 text-gray-500
                                                @endswitch
                                            ">{{ $hit['scan_type'] }}</span>
                                        @else
                                            <span class="text-gray-600">—</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-2 hidden md:table-cell text-gray-500">{{ $hit['status_code'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Cowrie Sessions --}}
        @if(!empty($cowrieSessions))
            <div class="bg-gray-900 border border-gray-800 rounded-lg mb-4">
                <div class="px-5 py-4 border-b border-gray-800 flex items-center justify-between">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Honeypot Sessions</p>
                    <span class="text-xs font-mono text-blue-400">{{ number_format($cowrieCount) }}</span>
                </div>
                <div class="divide-y divide-gray-800">
                    @foreach($cowrieSessions as $session)
                        <div class="px-5 py-3">
                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                <span class="text-xs font-mono text-gray-500">{{ $session['started_at'] ?? '—' }}</span>

                                @if($session['ip'])
                                    <a href="{{ route('ip-detail', $session['ip']) }}"
                                       class="text-xs font-mono text-blue-400 hover:text-blue-300 hover:underline transition-colors">{{ $session['ip'] }}</a>
                                @endif

                                @if($session['country_code'])
                                    <img src="https://flagcdn.com/16x12/{{ $session['country_code'] }}.png"
                                         alt="{{ $session['country'] ?? '' }}"
                                         class="w-4 h-3 object-cover rounded-sm opacity-70">
                                @endif

                                @if($session['country'])
                                    <span class="text-xs text-gray-500">{{ $session['country'] }}</span>
                                @endif

                                @if($session['username'])
                                    <span class="text-xs font-mono text-gray-400">{{ $session['username'] }}@if($session['password']):<span class="text-gray-600">{{ $session['password'] }}</span>@endif</span>
                                @endif

                                @if($session['duration_seconds'])
                                    <span class="text-xs text-gray-600">{{ round($session['duration_seconds'], 1) }}s</span>
                                @endif
                            </div>

                            @if(!empty($session['commands']))
                                <div class="mt-1.5 pl-2 border-l border-gray-800 space-y-0.5">
                                    @foreach($session['commands'] as $cmd)
                                        <p class="text-xs font-mono text-gray-400 break-all">{{ $cmd }}</p>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

    @endif
</div>
