<div>
    <h1 class="text-xl font-bold text-gray-100 mb-6">Databases</h1>

    {{-- Server stats --}}
    @if(!empty($serverStats))
        <div class="grid grid-cols-4 gap-3 mb-8">
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
                <p class="text-sm font-mono text-gray-100">{{ $serverStats['connections'] }}</p>
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
        <div class="bg-gray-900 border border-gray-800 rounded-lg divide-y divide-gray-800">
            @foreach($databases as $db)
                <div class="px-5 py-4 flex items-center justify-between">
                    <p class="text-sm font-mono text-gray-100">{{ $db['name'] }}</p>
                    <div class="flex items-center gap-10">
                        <div class="text-right">
                            <p class="text-xs text-gray-500 mb-0.5">Size</p>
                            <p class="text-sm text-gray-300">{{ $db['size_mb'] }} MB</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-gray-500 mb-0.5">Tables</p>
                            <p class="text-sm text-gray-300">{{ $db['tables'] }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-gray-500 mb-0.5">Rows</p>
                            <p class="text-sm text-gray-300">{{ number_format($db['rows']) }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>