<div @if($polling) wire:poll.10s="loadLines" @endif>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold text-gray-100">Logs</h1>

        <div class="flex items-center gap-4">
            {{-- Search --}}
            <input wire:model.live.debounce.300ms="search"
                   type="text"
                   placeholder="Search..."
                   class="bg-gray-900 border border-gray-700 rounded-lg px-3 py-1.5 text-sm text-gray-300 placeholder-gray-600 focus:outline-none focus:border-gray-500 font-mono w-48" />

            {{-- Download --}}
            <button wire:click="download"
                    class="text-xs text-gray-500 hover:text-gray-300 transition-colors font-mono">
                download
            </button>

            {{-- Poll toggle --}}
            <button wire:click="togglePolling"
                    class="flex items-center gap-2 text-xs font-mono transition-colors {{ $polling ? 'text-green-400' : 'text-gray-600' }}">
                <span class="w-2 h-2 rounded-full {{ $polling ? 'bg-green-400' : 'bg-gray-600' }}"></span>
                {{ $polling ? 'live' : 'paused' }}
            </button>
        </div>
    </div>

    {{-- Tab switcher --}}
    <div class="flex gap-1 mb-4 border-b border-gray-800 overflow-x-auto">
        @foreach($sources as $key => $source)
            <button wire:click="switchSource('{{ $key }}')"
                    class="px-4 py-2 text-xs font-mono whitespace-nowrap transition-colors {{ $activeSource === $key ? 'text-gray-100 border-b border-gray-100 -mb-px' : 'text-gray-500 hover:text-gray-300' }}">
                {{ $source['label'] }}
            </button>
        @endforeach
    </div>

    {{-- Log output --}}
    @if(empty($lines))
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-5">
            <p class="text-sm text-gray-500 font-mono">No log entries found.</p>
        </div>
    @else
        <div class="bg-gray-900 border border-gray-800 rounded-lg divide-y divide-gray-800/50">
            @foreach($lines as $line)
                <div class="px-4 py-2">
                    <p class="text-xs font-mono break-all leading-relaxed
                        {{ $line['level'] === 'error' ? 'text-red-400' : ($line['level'] === 'warning' ? 'text-amber-400' : 'text-gray-400') }}">
                        {{ $line['raw'] }}
                    </p>
                </div>
            @endforeach
        </div>
    @endif
</div>
