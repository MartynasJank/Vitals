<div>
    <h1 class="text-xl font-bold text-gray-100 mb-6">Services</h1>

    @if($restartMessage)
        <div class="mb-4 px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-300">
            {{ $restartMessage }}
        </div>
    @endif

    {{-- System Services --}}
    <div class="space-y-3 mb-10">
        @foreach($services as $key => $service)
            <div class="bg-gray-900 border border-gray-800 rounded-lg px-4 sm:px-5 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center gap-4 min-w-0">
                    <span class="w-2.5 h-2.5 rounded-full flex-shrink-0 {{ $service['restarting'] ? 'bg-amber-400' : ($service['running'] ? 'bg-green-400' : 'bg-red-500') }}"></span>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-100">{{ $service['label'] }}</p>
                        <p class="text-xs text-gray-500 font-mono mt-0.5 truncate">{{ $key }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-x-6 gap-y-2 sm:gap-x-10 sm:justify-end">
                    @if($service['uptime'])
                        <div class="text-right">
                            <p class="text-xs text-gray-500 mb-0.5">Uptime</p>
                            <p class="text-sm text-gray-300">{{ $service['uptime'] }}</p>
                        </div>
                    @endif

                    @if($service['memory'])
                        <div class="text-right hidden sm:block">
                            <p class="text-xs text-gray-500 mb-0.5">Memory</p>
                            <p class="text-sm text-gray-300">{{ $service['memory'] }}</p>
                        </div>
                    @endif

                    <div class="text-right">
                        <p class="text-xs text-gray-500 mb-0.5">Status</p>
                        <p class="text-sm font-medium {{ $service['restarting'] ? 'text-amber-400' : ($service['running'] ? 'text-green-400' : 'text-red-400') }}">
                            {{ $service['restarting'] ? 'Restarting…' : ($service['running'] ? 'Running' : 'Stopped') }}
                        </p>
                    </div>

                    <button wire:click="restart('{{ $key }}')"
                            wire:confirm="Restart {{ $service['label'] }}?"
                            class="text-xs text-gray-600 hover:text-amber-400 transition-colors font-mono">
                        restart
                    </button>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Cron Jobs --}}
    <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-3">Cron Jobs</h2>

    @if(empty($cronJobs))
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-5">
            <p class="text-sm text-gray-500">No cron jobs found.</p>
        </div>
    @else
        <div class="bg-gray-900 border border-gray-800 rounded-lg divide-y divide-gray-800">
            @foreach($cronJobs as $job)
                <div class="px-5 py-3.5 overflow-x-auto">
                    <p class="text-sm font-mono text-gray-300 whitespace-nowrap">{{ $job }}</p>
                </div>
            @endforeach
        </div>
    @endif
</div>
