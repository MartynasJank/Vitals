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
            <div class="bg-gray-900 border border-gray-800 rounded-lg overflow-hidden" x-data="{ journal: false }">

                {{-- Main row --}}
                <div class="px-4 sm:px-5 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex items-center gap-4 min-w-0">
                        <span class="w-2.5 h-2.5 rounded-full flex-shrink-0 {{ $service['restarting'] ? 'bg-amber-400' : ($service['running'] ? 'bg-green-400' : 'bg-red-500') }}"></span>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="text-sm font-medium text-gray-100">{{ $service['label'] }}</p>
                                {{-- Badges --}}
                                @if($key === 'fail2ban' && $failBanned > 0)
                                    <span class="text-xs font-mono px-1.5 py-0.5 rounded bg-red-900/30 text-red-400">{{ $failBanned }} banned</span>
                                @endif
                                @if($key === 'cowrie' && $cowrieActiveSessions > 0)
                                    <span class="text-xs font-mono px-1.5 py-0.5 rounded bg-amber-900/30 text-amber-400">{{ $cowrieActiveSessions }} active session{{ $cowrieActiveSessions !== 1 ? 's' : '' }}</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-3 mt-0.5 flex-wrap">
                                <p class="text-xs text-gray-600 font-mono">{{ $key }}</p>
                                @if(!empty($service['ports']))
                                    @foreach($service['ports'] as $port)
                                        <span class="text-xs font-mono text-gray-600">:{{ $port }}</span>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-x-6 gap-y-2 sm:gap-x-8 sm:justify-end">
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

                        @if($service['workers'])
                            <div class="text-right hidden sm:block">
                                <p class="text-xs text-gray-500 mb-0.5">Workers</p>
                                <p class="text-sm text-gray-300">{{ $service['workers'] }}</p>
                            </div>
                        @endif

                        @if($key === 'nginx' && isset($service['connections']))
                            <div class="text-right hidden sm:block">
                                <p class="text-xs text-gray-500 mb-0.5">Connections</p>
                                <p class="text-sm text-gray-300">{{ $service['connections'] }}</p>
                            </div>
                        @endif

                        @if($key === 'php8.4-fpm' && !empty($service['fpm']))
                            <div class="text-right hidden sm:block">
                                <p class="text-xs text-gray-500 mb-0.5">Pool</p>
                                <p class="text-sm text-gray-300">{{ $service['fpm']['active'] }}a / {{ $service['fpm']['idle'] }}i</p>
                            </div>
                        @endif

                        <div class="text-right">
                            <p class="text-xs text-gray-500 mb-0.5">Status</p>
                            <p class="text-sm font-medium {{ $service['restarting'] ? 'text-amber-400' : ($service['running'] ? 'text-green-400' : 'text-red-400') }}">
                                {{ $service['restarting'] ? 'Restarting…' : ($service['running'] ? 'Running' : 'Stopped') }}
                            </p>
                        </div>

                        @if(!empty($service['journal']))
                            <button @click="journal = !journal"
                                    class="text-xs font-mono text-gray-600 hover:text-gray-400 transition-colors"
                                    :class="{ 'text-gray-400': journal }">
                                logs
                            </button>
                        @endif

                        <button wire:click="restart('{{ $key }}')"
                                wire:confirm="Restart {{ $service['label'] }}?"
                                class="text-xs text-gray-600 hover:text-amber-400 transition-colors font-mono">
                            restart
                        </button>
                    </div>
                </div>

                {{-- Journal entries --}}
                @if(!empty($service['journal']))
                    <div x-show="journal" x-cloak class="border-t border-gray-800 bg-gray-950 px-4 py-3 overflow-x-auto">
                        @foreach($service['journal'] as $line)
                            <p class="text-xs font-mono text-gray-500 whitespace-nowrap leading-relaxed">{{ $line }}</p>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Queue Workers --}}
    <div class="flex items-center justify-between mb-3">
        <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider">Queue Workers</h2>
        @if($failedJobs > 0)
            <span class="text-xs font-mono px-1.5 py-0.5 rounded bg-red-900/30 text-red-400">{{ $failedJobs }} failed</span>
        @else
            <span class="text-xs font-mono text-gray-600">0 failed</span>
        @endif
    </div>

    @if(empty($queueWorkers))
        <div class="bg-gray-900 border border-gray-800 rounded-lg px-5 py-4 mb-4">
            <p class="text-sm text-gray-500">No queue workers running.</p>
        </div>
    @else
        <div class="bg-gray-900 border border-gray-800 rounded-lg divide-y divide-gray-800 mb-4">
            @foreach($queueWorkers as $worker)
                <div class="px-5 py-3 flex items-center gap-4">
                    <span class="w-2 h-2 rounded-full bg-green-400 flex-shrink-0"></span>
                    <span class="text-xs font-mono text-gray-600">pid {{ $worker['pid'] }}</span>
                    <span class="text-xs font-mono text-gray-400 truncate flex-1">{{ $worker['options'] ?: '--queue=default' }}</span>
                </div>
            @endforeach
        </div>
    @endif

    @if(!empty($recentFailedJobs))
        <div class="bg-gray-900 border border-red-900/30 rounded-lg divide-y divide-gray-800 mb-10">
            <div class="px-5 py-3">
                <p class="text-xs font-semibold text-red-500 uppercase tracking-wider">Recent Failures</p>
            </div>
            @foreach($recentFailedJobs as $job)
                <div class="px-5 py-3">
                    <div class="flex items-center gap-3 mb-1">
                        <span class="text-xs font-mono text-red-400">{{ $job['job'] }}</span>
                        <span class="text-xs font-mono px-1.5 py-0.5 rounded bg-gray-800 text-gray-500">{{ $job['queue'] }}</span>
                        <span class="text-xs text-gray-600 font-mono">{{ $job['failed_at'] }}</span>
                    </div>
                    @if($job['exception'])
                        <p class="text-xs font-mono text-gray-600 truncate">{{ $job['exception'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <div class="mb-10"></div>
    @endif

    {{-- Cron Jobs --}}
    <div class="flex items-center justify-between mb-3">
        <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider">Cron Jobs</h2>
        @if($schedulerLastRun)
            <span class="text-xs font-mono text-gray-600">scheduler {{ $schedulerLastRun }}</span>
        @endif
    </div>

    @if(empty($cronJobs))
        <div class="bg-gray-900 border border-gray-800 rounded-lg px-5 py-4 mb-4">
            <p class="text-sm text-gray-500">No cron jobs found.</p>
        </div>
    @else
        <div class="bg-gray-900 border border-gray-800 rounded-lg divide-y divide-gray-800 mb-4">
            @foreach($cronJobs as $i => $job)
                <div class="px-5 py-3 flex items-center gap-4">
                    <span class="text-xs font-mono text-amber-400 whitespace-nowrap flex-shrink-0">{{ $job['schedule'] }}</span>
                    <span class="text-xs font-mono text-gray-400 truncate flex-1">{{ $job['command'] }}</span>
                    <button wire:click="deleteCronJob({{ $i }})"
                            wire:confirm="Delete this cron job?"
                            class="text-xs font-mono text-gray-700 hover:text-red-400 transition-colors flex-shrink-0">
                        delete
                    </button>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Add cron form --}}
    <div class="bg-gray-900 border border-gray-800 rounded-lg px-5 py-4">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Add Cron Job</p>
        @if($cronError)
            <p class="text-xs text-red-400 font-mono mb-3">{{ $cronError }}</p>
        @endif
        <div class="flex flex-col sm:flex-row gap-3">
            <input wire:model="newCronSchedule"
                   type="text"
                   placeholder="*/5 * * * *"
                   class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm font-mono text-gray-300 placeholder-gray-600 focus:outline-none focus:border-gray-500 w-40 flex-shrink-0" />
            <input wire:model="newCronCommand"
                   type="text"
                   placeholder="/usr/bin/php8.4 /var/www/vitals/artisan some:command >> /dev/null 2>&1"
                   class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm font-mono text-gray-300 placeholder-gray-600 focus:outline-none focus:border-gray-500 flex-1" />
            <button wire:click="addCronJob"
                    class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-sm font-mono text-gray-200 rounded-lg transition-colors flex-shrink-0">
                Add
            </button>
        </div>
    </div>
</div>
