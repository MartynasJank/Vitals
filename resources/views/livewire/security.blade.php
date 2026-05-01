<div>
    <h1 class="text-xl font-bold text-gray-100 mb-6">Security</h1>

    @if($unbanMessage)
        <div class="mb-4 px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-300">
            {{ $unbanMessage }}
        </div>
    @endif

    {{-- Banned IPs --}}
    <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-3">Banned IPs</h2>

    @if(empty($bannedIps))
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-5 mb-8">
            <p class="text-sm text-gray-500">No banned IPs.</p>
        </div>
    @else
        <div class="bg-gray-900 border border-gray-800 rounded-lg divide-y divide-gray-800 mb-8">
            @foreach($bannedIps as $entry)
                <div class="px-5 py-3.5 flex items-center justify-between">
                    <div class="flex items-center gap-6">
                        <p class="text-sm font-mono text-gray-100">{{ $entry['ip'] }}</p>
                        <span class="text-xs text-gray-500 font-mono">{{ $entry['jail'] }}</span>
                    </div>
                    <button wire:click="unban('{{ $entry['ip'] }}', '{{ $entry['jail'] }}')"
                            wire:confirm="Unban {{ $entry['ip'] }}?"
                            class="text-xs text-gray-600 hover:text-amber-400 transition-colors font-mono">
                        unban
                    </button>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Failed Logins --}}
    <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-3">Recent Failed SSH Logins</h2>

    @if(empty($failedLogins))
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-5 mb-8">
            <p class="text-sm text-gray-500">No recent failed logins found.</p>
        </div>
    @else
        <div class="bg-gray-900 border border-gray-800 rounded-lg divide-y divide-gray-800 mb-8">
            @foreach($failedLogins as $entry)
                <div class="px-4 sm:px-5 py-3">
                    <div class="flex items-center gap-3">
                        <p class="text-sm font-mono text-red-400">{{ $entry['ip'] }}</p>
                        <span class="text-gray-600">·</span>
                        <p class="text-sm font-mono text-gray-300">{{ $entry['user'] }}</p>
                    </div>
                    <p class="text-xs font-mono text-gray-500 mt-0.5">{{ $entry['time'] }}</p>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Firewall Rules --}}
    <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-3">Firewall Rules (UFW)</h2>

    @if(empty($firewallRules))
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-5 mb-8">
            <p class="text-sm text-gray-500">No firewall rules found.</p>
        </div>
    @else
        <div class="bg-gray-900 border border-gray-800 rounded-lg divide-y divide-gray-800 mb-8">
            @foreach($firewallRules as $rule)
                <div class="px-5 py-3">
                    <p class="text-sm font-mono text-gray-300">{{ $rule }}</p>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Successful Logins --}}
    <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-3">Recent Successful SSH Logins</h2>

    @if(empty($successfulLogins))
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-5">
            <p class="text-sm text-gray-500">No recent successful logins found.</p>
        </div>
    @else
        <div class="bg-gray-900 border border-gray-800 rounded-lg divide-y divide-gray-800">
            @foreach($successfulLogins as $entry)
                <div class="px-4 sm:px-5 py-3">
                    <div class="flex items-center gap-3">
                        <p class="text-sm font-mono text-green-400">{{ $entry['ip'] }}</p>
                        <span class="text-gray-600">·</span>
                        <p class="text-sm font-mono text-gray-300">{{ $entry['user'] }}</p>
                    </div>
                    <p class="text-xs font-mono text-gray-500 mt-0.5">{{ $entry['time'] }}</p>
                </div>
            @endforeach
        </div>
    @endif
</div>
