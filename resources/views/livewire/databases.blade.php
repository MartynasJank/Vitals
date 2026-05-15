<div>
    <h1 class="text-xl font-bold text-gray-100 mb-6">Databases</h1>

    {{-- Server stats --}}
    @if(!empty($serverStats))
        <div class="grid grid-cols-2 gap-3 mb-4 sm:grid-cols-4">
            <div class="bg-gray-900 border border-gray-800 rounded-lg px-5 py-4">
                <p class="text-xs text-gray-500 mb-1">Version</p>
                <p class="text-sm font-mono text-gray-100">{{ $serverStats['version'] }}</p>
            </div>
            <div class="bg-gray-900 border border-gray-800 rounded-lg px-5 py-4">
                <p class="text-xs text-gray-500 mb-1">Uptime</p>
                <p class="text-sm font-mono text-gray-100">{{ $serverStats['uptime'] }}</p>
            </div>
            <div class="bg-gray-900 border border-gray-800 rounded-lg px-5 py-4">
                <p class="text-xs text-gray-500 mb-1">Connections</p>
                <p class="text-sm font-mono text-gray-100">{{ $serverStats['connections'] }} / {{ $serverStats['max_connections'] }}</p>
            </div>
            <div class="bg-gray-900 border border-gray-800 rounded-lg px-5 py-4">
                <p class="text-xs text-gray-500 mb-1">Threads Running</p>
                <p class="text-sm font-mono text-gray-100">{{ $serverStats['threads_running'] }}</p>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3 mb-8 sm:grid-cols-4">
            <div class="bg-gray-900 border border-gray-800 rounded-lg px-5 py-4">
                <p class="text-xs text-gray-500 mb-1">Buffer Hit Rate</p>
                <p class="text-sm font-mono {{ (float) $serverStats['buffer_hit_rate'] >= 99 ? 'text-green-400' : ((float) $serverStats['buffer_hit_rate'] >= 95 ? 'text-amber-400' : 'text-red-400') }}">
                    {{ $serverStats['buffer_hit_rate'] }}
                </p>
            </div>
            <div class="bg-gray-900 border border-gray-800 rounded-lg px-5 py-4">
                <p class="text-xs text-gray-500 mb-1">Slow Queries</p>
                <p class="text-sm font-mono {{ $serverStats['slow_queries'] > 0 ? 'text-amber-400' : 'text-gray-100' }}">
                    {{ number_format($serverStats['slow_queries']) }}
                </p>
            </div>
            <div class="bg-gray-900 border border-gray-800 rounded-lg px-5 py-4">
                <p class="text-xs text-gray-500 mb-1">Total Queries</p>
                <p class="text-sm font-mono text-gray-100">{{ number_format($serverStats['queries']) }}</p>
            </div>
        </div>
    @endif

    {{-- Database list --}}
    <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-3">Databases</h2>

    @if(empty($databases))
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-5">
            <p class="text-sm text-gray-500">No databases found.</p>
        </div>
    @else
        <div class="space-y-2">
            @foreach($databases as $db)
                <div class="bg-gray-900 border border-gray-800 rounded-lg overflow-hidden" x-data="{ open: false }">
                    {{-- Database row --}}
                    <button @click="open = !open" class="w-full px-5 py-4 flex items-center justify-between hover:bg-gray-800/50 transition-colors">
                        <div class="flex items-center gap-3">
                            <svg class="w-3 h-3 text-gray-500 transition-transform" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                            <p class="text-sm font-mono text-gray-100">{{ $db['name'] }}</p>
                        </div>
                        <div class="flex items-center gap-4 sm:gap-10 flex-shrink-0">
                            <div class="text-right">
                                <p class="text-xs text-gray-500 mb-0.5">Size</p>
                                <p class="text-sm text-gray-300">{{ $db['size_mb'] }} MB</p>
                            </div>
                            <div class="text-right hidden sm:block">
                                <p class="text-xs text-gray-500 mb-0.5">Tables</p>
                                <p class="text-sm text-gray-300">{{ $db['table_count'] }}</p>
                            </div>
                            <div class="text-right hidden sm:block">
                                <p class="text-xs text-gray-500 mb-0.5">Rows</p>
                                <p class="text-sm text-gray-300">{{ number_format($db['rows']) }}</p>
                            </div>
                        </div>
                    </button>

                    {{-- Tables dropdown --}}
                    <div x-show="open" x-cloak class="border-t border-gray-800 overflow-x-auto">
                        <div class="px-5 py-2 grid grid-cols-4 gap-4 min-w-[400px]">
                            <p class="text-xs text-gray-600 uppercase tracking-wider">Table</p>
                            <p class="text-xs text-gray-600 uppercase tracking-wider text-right">Engine</p>
                            <p class="text-xs text-gray-600 uppercase tracking-wider text-right">Rows</p>
                            <p class="text-xs text-gray-600 uppercase tracking-wider text-right">Size</p>
                        </div>
                        @foreach($db['tables'] as $table)
                            <div class="px-5 py-2.5 grid grid-cols-4 gap-4 border-t border-gray-800/60 min-w-[400px]">
                                <p class="text-xs font-mono text-gray-300">{{ $table['name'] }}</p>
                                <p class="text-xs font-mono text-gray-500 text-right">{{ $table['engine'] }}</p>
                                <p class="text-xs font-mono text-gray-400 text-right">{{ number_format($table['rows']) }}</p>
                                <p class="text-xs font-mono text-gray-400 text-right">{{ $table['size_mb'] }} MB</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Process list --}}
    <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mt-10 mb-3">Active Queries</h2>
    @if(empty($processList))
        <div class="bg-gray-900 border border-gray-800 rounded-lg px-5 py-4">
            <p class="text-sm text-gray-500">No active queries.</p>
        </div>
    @else
        <div class="bg-gray-900 border border-gray-800 rounded-lg overflow-x-auto">
            <table class="w-full text-xs font-mono min-w-[640px]">
                <thead>
                    <tr class="border-b border-gray-800">
                        <th class="px-4 py-2.5 text-left text-gray-500 font-medium">ID</th>
                        <th class="px-4 py-2.5 text-left text-gray-500 font-medium">User</th>
                        <th class="px-4 py-2.5 text-left text-gray-500 font-medium">DB</th>
                        <th class="px-4 py-2.5 text-left text-gray-500 font-medium">Command</th>
                        <th class="px-4 py-2.5 text-right text-gray-500 font-medium">Time (s)</th>
                        <th class="px-4 py-2.5 text-left text-gray-500 font-medium">State</th>
                        <th class="px-4 py-2.5 text-left text-gray-500 font-medium">Query</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    @foreach($processList as $process)
                        <tr class="{{ $process['time'] >= 5 ? 'bg-amber-900/10' : '' }}">
                            <td class="px-4 py-2.5 text-gray-500">{{ $process['id'] }}</td>
                            <td class="px-4 py-2.5 text-gray-300">{{ $process['user'] }}</td>
                            <td class="px-4 py-2.5 text-gray-400">{{ $process['db'] ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-blue-400">{{ $process['command'] }}</td>
                            <td class="px-4 py-2.5 text-right {{ $process['time'] >= 5 ? 'text-amber-400' : 'text-gray-300' }}">{{ $process['time'] }}</td>
                            <td class="px-4 py-2.5 text-gray-400">{{ $process['state'] ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-gray-300 max-w-xs truncate">{{ $process['info'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Slow queries --}}
    <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mt-10 mb-3">Slowest Queries</h2>
    @if(empty($slowQueries))
        <div class="bg-gray-900 border border-gray-800 rounded-lg px-5 py-4">
            <p class="text-sm text-gray-500">No query data available.</p>
        </div>
    @else
        <div class="bg-gray-900 border border-gray-800 rounded-lg overflow-x-auto">
            <table class="w-full text-xs font-mono min-w-[640px]">
                <thead>
                    <tr class="border-b border-gray-800">
                        <th class="px-4 py-2.5 text-left text-gray-500 font-medium">Query</th>
                        <th class="px-4 py-2.5 text-left text-gray-500 font-medium">DB</th>
                        <th class="px-4 py-2.5 text-right text-gray-500 font-medium">Count</th>
                        <th class="px-4 py-2.5 text-right text-gray-500 font-medium">Avg (ms)</th>
                        <th class="px-4 py-2.5 text-right text-gray-500 font-medium">Max (ms)</th>
                        <th class="px-4 py-2.5 text-right text-gray-500 font-medium">Total (ms)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    @foreach($slowQueries as $query)
                        <tr>
                            <td class="px-4 py-2.5 text-gray-300 max-w-sm truncate" title="{{ $query['query'] }}">{{ $query['query'] }}</td>
                            <td class="px-4 py-2.5 text-gray-400">{{ $query['schema'] ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-right text-gray-400">{{ number_format($query['count']) }}</td>
                            <td class="px-4 py-2.5 text-right {{ $query['avg_ms'] >= 1000 ? 'text-red-400' : ($query['avg_ms'] >= 100 ? 'text-amber-400' : 'text-gray-300') }}">{{ number_format($query['avg_ms'], 2) }}</td>
                            <td class="px-4 py-2.5 text-right text-gray-400">{{ number_format($query['max_ms'], 2) }}</td>
                            <td class="px-4 py-2.5 text-right text-gray-400">{{ number_format($query['total_ms'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- InnoDB status --}}
    <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mt-10 mb-3">InnoDB Status</h2>
    <div class="grid grid-cols-2 gap-3 mb-4 sm:grid-cols-4">
        <div class="bg-gray-900 border border-gray-800 rounded-lg px-5 py-4">
            <p class="text-xs text-gray-500 mb-1">Active Transactions</p>
            <p class="text-sm font-mono {{ $innodbStatus['active_transactions'] > 0 ? 'text-amber-400' : 'text-gray-100' }}">{{ $innodbStatus['active_transactions'] }}</p>
        </div>
        <div class="bg-gray-900 border border-gray-800 rounded-lg px-5 py-4">
            <p class="text-xs text-gray-500 mb-1">Lock Waits</p>
            <p class="text-sm font-mono {{ $innodbStatus['lock_waits'] > 0 ? 'text-red-400' : 'text-gray-100' }}">{{ $innodbStatus['lock_waits'] }}</p>
        </div>
        <div class="bg-gray-900 border border-gray-800 rounded-lg px-5 py-4">
            <p class="text-xs text-gray-500 mb-1">History List Length</p>
            <p class="text-sm font-mono {{ ($innodbStatus['history_list_length'] ?? 0) > 1000 ? 'text-red-400' : (($innodbStatus['history_list_length'] ?? 0) > 100 ? 'text-amber-400' : 'text-gray-100') }}">
                {{ $innodbStatus['history_list_length'] ?? '—' }}
            </p>
        </div>
        <div class="bg-gray-900 border border-gray-800 rounded-lg px-5 py-4">
            <p class="text-xs text-gray-500 mb-1">Last Deadlock</p>
            <p class="text-sm font-mono {{ $innodbStatus['last_deadlock'] ? 'text-red-400' : 'text-gray-100' }}">{{ $innodbStatus['last_deadlock'] ? 'Detected' : 'None' }}</p>
        </div>
    </div>
    @if($innodbStatus['last_deadlock'])
        <div class="bg-gray-900 border border-red-900/50 rounded-lg px-5 py-4">
            <p class="text-xs text-red-400 font-medium mb-2">Latest Detected Deadlock</p>
            <pre class="text-xs font-mono text-gray-400 whitespace-pre-wrap break-all">{{ $innodbStatus['last_deadlock'] }}</pre>
        </div>
    @endif
</div>