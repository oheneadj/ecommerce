<x-filament-panels::page>
    <div class="flex flex-wrap items-center gap-4">
        <div class="flex items-center gap-3 rounded-lg border px-4 py-3 {{ $criticalCount > 0 ? 'border-red-400 bg-red-50 dark:border-red-600 dark:bg-red-950/40' : 'border-green-400 bg-green-50 dark:border-green-600 dark:bg-green-950/40' }}">
            <span class="text-2xl font-bold {{ $criticalCount > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                {{ $criticalCount > 0 ? 'CRITICAL' : 'HEALTHY' }}
            </span>
            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $weightedPercentage }}%</span>
        </div>

        <div class="flex gap-4 text-sm">
            <span class="text-red-600 dark:text-red-400">{{ $criticalCount }} critical</span>
            <span class="text-amber-600 dark:text-amber-400">{{ $warningCount }} warning</span>
            <span class="text-green-600 dark:text-green-400">{{ $passingCount }} passing</span>
        </div>

        <x-filament::button wire:click="rerunChecks" wire:loading.attr="disabled" wire:target="rerunChecks" icon="heroicon-o-arrow-path" outlined>
            <span wire:loading.remove wire:target="rerunChecks">Re-run checks</span>
            <span wire:loading wire:target="rerunChecks">Re-running checks…</span>
        </x-filament::button>

        @if ($criticalCount > 0)
            @if ($alertsSnoozedUntil && $alertsSnoozedUntil->isFuture())
                <x-filament::button wire:click="resumeAlerts" wire:loading.attr="disabled" wire:target="resumeAlerts" icon="heroicon-o-bell-alert" color="success" outlined>
                    <span wire:loading.remove wire:target="resumeAlerts">Resume daily alerts (snoozed until {{ $alertsSnoozedUntil->format('d M, H:i') }})</span>
                    <span wire:loading wire:target="resumeAlerts">Resuming…</span>
                </x-filament::button>
            @else
                <x-filament::button wire:click="snoozeAlerts" wire:loading.attr="disabled" wire:target="snoozeAlerts" icon="heroicon-o-bell-slash" color="warning" outlined>
                    <span wire:loading.remove wire:target="snoozeAlerts">Snooze daily alerts for 24 hours</span>
                    <span wire:loading wire:target="snoozeAlerts">Snoozing…</span>
                </x-filament::button>
            @endif
        @endif
    </div>

    @foreach (['Infrastructure', 'Operations', 'Configuration', 'Data Integrity'] as $category)
        @if (! empty($groupedResults[$category] ?? []))
            <x-filament::section :heading="$category" class="mt-6">
                <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($groupedResults[$category] as $check)
                        <div class="flex flex-wrap items-start justify-between gap-2 py-3">
                            <div>
                                <p class="font-medium">{{ $check['name'] }}</p>
                                @if (! empty($check['message']))
                                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $check['message'] }}</p>
                                @endif
                                @if ($category === 'Data Integrity' && ! empty($check['ran_at']))
                                    <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">Last run: {{ $check['ran_at']->diffForHumans() }}</p>
                                @endif
                            </div>
                            <x-filament::badge :color="match ($check['status']) {
                                'failed', 'crashed' => 'danger',
                                'warning' => 'warning',
                                default => 'success',
                            }">
                                {{ ucfirst($check['status']) }}
                            </x-filament::badge>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif
    @endforeach

    <x-filament::section heading="Attestations" class="mt-6">
        <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
            @foreach ($attestationRows as $row)
                <div class="flex flex-wrap items-center justify-between gap-2 py-3">
                    <div>
                        <p class="font-medium">{{ $row['label'] }}</p>
                        @if ($row['latest'])
                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                Last confirmed by {{ $row['latest']->confirmedBy->name ?? 'Unknown' }} on {{ $row['latest']->confirmed_at->format('d M Y') }}
                                @if ($row['is_stale'])
                                    <span class="text-red-600 dark:text-red-400">(stale)</span>
                                @endif
                            </p>
                        @else
                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Never confirmed.</p>
                        @endif
                    </div>

                    <div class="flex items-center gap-2">
                        <x-filament::badge :color="$row['latest'] && ! $row['is_stale'] ? 'success' : 'danger'">
                            {{ $row['latest'] && ! $row['is_stale'] ? 'Confirmed' : 'Critical' }}
                        </x-filament::badge>

                        <x-filament::button
                            size="sm"
                            outlined
                            wire:click="mountAction('recordAttestation', { key: '{{ $row['key'] }}' })"
                        >
                            Record attestation
                        </x-filament::button>
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-panels::page>
