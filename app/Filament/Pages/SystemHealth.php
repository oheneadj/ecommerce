<?php

/**
 * Super-Admin-only system health dashboard (docs/TASK-system-health-checks.md Step 5.2).
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\Health\ListCriticalHealthFailures;
use App\Enums\UserRole;
use App\Models\HealthAttestation;
use App\Models\IntegrityCheckResult;
use App\Models\StoreSetting;
use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Spatie\Health\Checks\Check;
use Spatie\Health\Enums\Status;
use Spatie\Health\Facades\Health;
use Throwable;
use UnitEnum;

/**
 * Tier 1/2 checks (Health::registeredChecks()) are run live on every visit
 * — they're cheap by design (docs/TASK-system-health-checks.md's own tier
 * table). Tier 3 checks are never re-run here; this page only reads their
 * last stored result from `integrity_check_results`, populated nightly by
 * `health:run-integrity-checks` — running a full-table aggregate scan on
 * page load is exactly what Tier 3 must never do.
 */
class SystemHealth extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.system-health';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $title = 'System Health';

    /** @var array<string, array<int, array<string, mixed>>> */
    public array $groupedResults = [];

    /** @var array<int, array<string, mixed>> */
    public array $attestationRows = [];

    public int $criticalCount = 0;

    public int $warningCount = 0;

    public int $passingCount = 0;

    public float $weightedPercentage = 0;

    public ?CarbonInterface $alertsSnoozedUntil = null;

    private const CATEGORY_MAP = [
        // Built-in Spatie checks (getName() strips the trailing "Check").
        'DebugMode' => 'Configuration',
        'Environment' => 'Configuration',
        'OptimizedApp' => 'Configuration',
        'Database' => 'Infrastructure',
        'UsedDiskSpace' => 'Infrastructure',
        'Schedule' => 'Operations',
        'Queue' => 'Operations',

        // This app's own Tier 1/2 checks.
        'DatabaseEngineIsInnoDb' => 'Infrastructure',
        'TransactionDurabilityEnabled' => 'Infrastructure',
        'TransactionIsolationLevelIsSafe' => 'Infrastructure',
        'ForeignKeysAreEnforced' => 'Infrastructure',
        'StorageIsWritableAndLinked' => 'Infrastructure',
        'PaymentProvidersConfigured' => 'Configuration',
        'SmsProviderConfigured' => 'Configuration',
        'StoreSettingsPopulated' => 'Configuration',
        'StaticPagesHaveContent' => 'Configuration',
        'SuperAdminExists' => 'Configuration',
        'ExpiredReservationsAreBeingReleased' => 'Operations',
        'PendingPaymentsAreBeingVerified' => 'Operations',
        'BackupIsRecent' => 'Operations',
    ];

    private const CATEGORY_ORDER = ['Infrastructure', 'Operations', 'Configuration', 'Data Integrity', 'Attestations'];

    /**
     * Runs every check once, on first page load.
     */
    public function mount(): void
    {
        $this->runChecks();
    }

    /**
     * Re-runs every Tier 1/2 check on demand and re-reads the stored Tier
     * 3/attestation state, refreshing the whole page's results in place.
     */
    public function rerunChecks(): void
    {
        $this->runChecks();

        Notification::make()->title('Checks re-run')->success()->send();
    }

    /**
     * Mutes the daily critical-alert notification for 24 hours — but only
     * for the failures currently on screen. Recording which ones those
     * were means SendCriticalHealthAlert can still alert immediately if
     * something new and unrelated breaks during the snooze window, instead
     * of a single global timestamp silencing everything for a full day.
     */
    public function snoozeAlerts(): void
    {
        StoreSetting::current()->update([
            'health_alerts_snoozed_until' => now()->addDay(),
            'health_alerts_snoozed_failures' => ListCriticalHealthFailures::run(),
        ]);

        $this->runChecks();

        Notification::make()->title('Alerts snoozed for 24 hours')->success()->send();
    }

    /**
     * Clears an active snooze so the daily critical-alert notification resumes immediately.
     */
    public function resumeAlerts(): void
    {
        StoreSetting::current()->update([
            'health_alerts_snoozed_until' => null,
            'health_alerts_snoozed_failures' => null,
        ]);

        $this->runChecks();

        Notification::make()->title('Alerts resumed')->success()->send();
    }

    /**
     * The modal action a Super Admin uses to record a new attestation for
     * a given key (passed as a mounted argument from the Blade view).
     */
    public function recordAttestationAction(): Action
    {
        return Action::make('recordAttestation')
            ->label('Record attestation')
            ->schema([
                Textarea::make('notes')
                    ->label('Notes')
                    ->placeholder('What was tested, and how.'),
            ])
            ->action(function (array $data, array $arguments): void {
                HealthAttestation::query()->create([
                    'key' => $arguments['key'],
                    'confirmed_by' => Auth::id(),
                    'confirmed_at' => now(),
                    'notes' => $data['notes'] ?? null,
                ]);

                $this->runChecks();

                Notification::make()->title('Attestation recorded')->success()->send();
            });
    }

    /**
     * Rebuilds every piece of state the view reads: live Tier 1/2 results
     * grouped by category, the stored Tier 3 results, attestation rows,
     * the snooze timestamp, and the summary counts.
     */
    private function runChecks(): void
    {
        $groups = [];

        foreach (self::CATEGORY_ORDER as $category) {
            $groups[$category] = [];
        }

        foreach (Health::registeredChecks() as $check) {
            /** @var Check $check */
            if (! $check->shouldRun()) {
                continue;
            }

            try {
                $result = $check->run();
                $status = $result->status->value;
                $message = $result->getNotificationMessage();
            } catch (Throwable $e) {
                $status = 'failed';
                $message = $e->getMessage();
            }

            $category = self::CATEGORY_MAP[$check->getName()] ?? 'Configuration';

            $groups[$category][] = [
                'name' => $check->getLabel(),
                'status' => $status,
                'message' => $message,
            ];
        }

        foreach (IntegrityCheckResult::query()->orderBy('check_name')->get() as $integrityResult) {
            $groups['Data Integrity'][] = [
                'name' => $integrityResult->check_name,
                'status' => $integrityResult->status,
                'message' => $integrityResult->message,
                'ran_at' => $integrityResult->ran_at,
                'violation_count' => $integrityResult->violation_count,
            ];
        }

        $this->groupedResults = $groups;
        $this->attestationRows = $this->buildAttestationRows();
        $this->alertsSnoozedUntil = StoreSetting::current()->health_alerts_snoozed_until;
        $this->calculateSummary();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildAttestationRows(): array
    {
        return collect(HealthAttestation::REQUIRED)->map(function (array $definition, string $key): array {
            $latest = HealthAttestation::latestFor($key);

            return [
                'key' => $key,
                'label' => $definition['label'],
                'latest' => $latest,
                'is_stale' => $latest?->isStale() ?? false,
            ];
        })->values()->all();
    }

    /**
     * Tallies critical/warning/passing counts across both the grouped
     * check results and the attestation rows, and derives the
     * severity-weighted percentage shown in the summary badge.
     */
    private function calculateSummary(): void
    {
        $critical = 0;
        $warning = 0;
        $passing = 0;

        foreach ($this->groupedResults as $checks) {
            foreach ($checks as $check) {
                match ($check['status']) {
                    Status::failed()->value, Status::crashed()->value, 'failed' => $critical++,
                    Status::warning()->value, 'warning' => $warning++,
                    default => $passing++,
                };
            }
        }

        foreach ($this->attestationRows as $row) {
            if ($row['latest'] === null || $row['is_stale']) {
                $critical++;
            } else {
                $passing++;
            }
        }

        $total = $critical + $warning + $passing;

        $this->criticalCount = $critical;
        $this->warningCount = $warning;
        $this->passingCount = $passing;
        $this->weightedPercentage = $total > 0 ? round(($passing + ($warning * 0.5)) / $total * 100, 1) : 100.0;
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(UserRole::SuperAdmin->value) ?? false;
    }
}
