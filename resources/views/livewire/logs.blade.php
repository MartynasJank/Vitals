<div class="overflow-x-hidden" @if($polling) wire:poll.10s="loadLines" @endif>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <h1 class="text-xl font-bold text-gray-100">Logs</h1>

        <div class="flex items-center gap-3 flex-wrap">
            {{-- Search --}}
            <input wire:model.live.debounce.300ms="search"
                   type="text"
                   placeholder="Search..."
                   class="bg-gray-900 border border-gray-700 rounded-lg px-3 py-1.5 text-sm text-gray-300 placeholder-gray-600 focus:outline-none focus:border-gray-500 font-mono w-36 sm:w-48" />

            {{-- Line count --}}
            <select wire:model.live="lineCount"
                    class="bg-gray-900 border border-gray-700 rounded-lg px-3 py-1.5 text-xs font-mono text-gray-400 focus:outline-none focus:border-gray-500">
                <option value="50">50 lines</option>
                <option value="100">100 lines</option>
                <option value="500">500 lines</option>
                <option value="1000">1000 lines</option>
            </select>

            {{-- Level filter --}}
            <div class="flex items-center gap-1 font-mono text-xs">
                <button wire:click="$set('levelFilter', 'all')"
                        class="px-2 py-1 rounded transition-colors {{ $levelFilter === 'all' ? 'text-gray-100 bg-gray-700' : 'text-gray-500 hover:text-gray-300' }}">all</button>
                <button wire:click="$set('levelFilter', 'error')"
                        class="px-2 py-1 rounded transition-colors {{ $levelFilter === 'error' ? 'text-red-400 bg-red-900/30' : 'text-gray-500 hover:text-gray-300' }}">error</button>
                <button wire:click="$set('levelFilter', 'warning')"
                        class="px-2 py-1 rounded transition-colors {{ $levelFilter === 'warning' ? 'text-amber-400 bg-amber-900/30' : 'text-gray-500 hover:text-gray-300' }}">warn</button>
            </div>

            {{-- Wrap toggle --}}
            <button wire:click="toggleWrap"
                    class="text-xs font-mono transition-colors {{ $wordWrap ? 'text-gray-400' : 'text-gray-600' }}">
                {{ $wordWrap ? 'wrap' : 'nowrap' }}
            </button>

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

    {{-- Source selector --}}
    <div class="flex items-center gap-3 mb-4">
        <select wire:change="switchSource($event.target.value)"
                class="bg-gray-900 border border-gray-700 rounded-lg px-3 py-1.5 text-sm font-mono text-gray-300 focus:outline-none focus:border-gray-500">
            @foreach($sources as $key => $source)
                <option value="{{ $key }}" {{ $activeSource === $key ? 'selected' : '' }}>{{ $source['label'] }}</option>
            @endforeach
        </select>
        @if($activeSource && isset($sources[$activeSource]))
            <span class="text-xs font-mono text-gray-600">
                {{ $sources[$activeSource]['size'] }} · {{ $sources[$activeSource]['modified'] }}
            </span>
        @endif
    </div>

    {{-- Log output --}}
    @if(empty($lines))
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-5">
            <p class="text-sm text-gray-500 font-mono">No log entries found.</p>
        </div>
    @else
        <div class="bg-gray-900 border border-gray-800 rounded-lg divide-y divide-gray-800/50 {{ ! $wordWrap ? 'overflow-x-auto' : '' }}">
            @foreach($lines as $line)
                @if($line['type'] === 'laravel')
                    <div class="px-4 py-2.5">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-xs font-mono text-gray-600">{{ $line['timestamp'] }}</span>
                            <span class="text-xs font-mono px-1.5 py-0.5 rounded
                                {{ $line['level'] === 'error' ? 'text-red-400 bg-red-900/20' : ($line['level'] === 'warning' ? 'text-amber-400 bg-amber-900/20' : 'text-blue-400 bg-blue-900/20') }}">
                                {{ $line['level_name'] }}
                            </span>
                        </div>
                        <p class="text-xs font-mono text-gray-300 leading-relaxed {{ $wordWrap ? 'break-all' : 'whitespace-nowrap' }}">{{ $line['message'] }}</p>
                        @if($line['exception'])
                            <p class="text-xs font-mono text-gray-500 mt-0.5">{{ $line['exception'] }}</p>
                        @endif
                    </div>
                @else
                    <div class="px-4 py-2">
                        <p class="text-xs font-mono leading-relaxed {{ $wordWrap ? 'break-all' : 'whitespace-nowrap' }}
                            {{ $line['level'] === 'error' ? 'text-red-400' : ($line['level'] === 'warning' ? 'text-amber-400' : 'text-gray-400') }}">
                            {{ $line['raw'] }}
                        </p>
                    </div>
                @endif
            @endforeach
        </div>
    @endif
</div>