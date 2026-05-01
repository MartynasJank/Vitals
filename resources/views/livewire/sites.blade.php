<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold text-gray-100">Sites</h1>
        <button wire:click="check" class="text-xs text-gray-500 hover:text-gray-300 transition-colors">
            Check all
        </button>
    </div>

    @if(empty($sites))
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-8 text-center">
            <p class="text-gray-500 text-sm">No sites discovered. Make sure Nginx is running and sites are enabled.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach($sites as $site)
                <div class="bg-gray-900 border border-gray-800 rounded-lg px-4 sm:px-5 py-4 flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="w-2 h-2 rounded-full flex-shrink-0 {{ $site['status'] === 'up' ? 'bg-green-400' : 'bg-red-500' }}"></span>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-100">{{ $site['name'] }}</p>
                            <p class="text-xs text-gray-500 font-mono truncate">{{ $site['url'] }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-4 sm:gap-6 flex-shrink-0">
                        <div class="text-right">
                            <p class="text-xs text-gray-500">Status</p>
                            <p class="text-sm font-medium {{ $site['status'] === 'up' ? 'text-green-400' : 'text-red-400' }}">
                                {{ strtoupper($site['status']) }}
                                @if($site['status_code'])
                                    <span class="text-gray-500 font-normal ml-1 hidden sm:inline">({{ $site['status_code'] }})</span>
                                @endif
                            </p>
                        </div>

                        <div class="text-right hidden sm:block">
                            <p class="text-xs text-gray-500">Response</p>
                            <p class="text-sm text-gray-300">
                                {{ $site['response_ms'] ? $site['response_ms'].'ms' : '—' }}
                            </p>
                        </div>

                        <button wire:click="checkNow('{{ $site['url'] }}')"
                                class="text-xs text-gray-600 hover:text-gray-300 transition-colors font-mono">
                            check
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
