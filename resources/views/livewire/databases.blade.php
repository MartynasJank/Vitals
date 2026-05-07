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
</div>