<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold text-gray-100">Logs</h1>
        <input wire:model.live.debounce.300ms="search"
               type="text"
               placeholder="Search..."
               class="bg-gray-900 border border-gray-700 rounded-lg px-3 py-1.5 text-sm text-gray-300 placeholder-gray-600 focus:outline-none focus:border-gray-500 font-mono w-56" />
    </div>

    {{-- Tab switcher --}}
    <div class="flex gap-1 mb-4 border-b border-gray-800">
        @foreach($sources as $key => $source)
            <button wire:click="switchSource('{{ $key }}')"
                    class="px-4 py-2 text-xs font-mono transition-colors {{ $activeSource === $key ? 'text-gray-100 border-b border-gray-100 -mb-px' : 'text-gray-500 hover:text-gray-300' }}">
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
