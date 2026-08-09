<?php

/**
 * Super-Admin-only system health dashboard (docs/TASK-system-health-checks.md Step 5.2).
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\HealthAttestation;
use App\Models\IntegrityCheckResult;
use BackedEnum;
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
    ];

    private const CATEGORY_ORDER = ['Infrastructure', 'Operations', 'Configuration', 'Data Integrity', 'Attestations'];

    public function mount(): void
    {
        $this->runChecks();
    }

    public function rerunChecks(): void
    {
        $this->runChecks();

        Notification::make()->title('Checks re-run')->success()->send();
    }

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

            $result = $check->run();
            $category = self::CATEGORY_MAP[$check->getName()] ?? 'Configuration';

            $groups[$category][] = [
                'name' => $check->getLabel(),
                'status' => $result->status->value,
                'message' => $result->getNotificationMessage(),
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
