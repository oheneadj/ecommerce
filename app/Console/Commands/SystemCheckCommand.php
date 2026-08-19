<?php

/**
 * The deploy-gate command (docs/TASK-system-health-checks.md Step 6.1).
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\HealthChecks\ExpiredReservationsAreBeingReleased;
use App\HealthChecks\PendingPaymentsAreBeingVerified;
use App\Models\IntegrityCheckResult;
use Illuminate\Console\Command;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Checks\QueueCheck;
use Spatie\Health\Checks\Checks\ScheduleCheck;
use Spatie\Health\Enums\Status;
use Spatie\Health\Facades\Health;
use Throwable;

/**
 * `--critical` is meant to be wired into the post-deploy step so a failing
 * deploy aborts — but Tier 2 heartbeat checks are EXCLUDED from that gate
 * by default: immediately after a deploy, the queue worker has just
 * restarted and the scheduler hasn't ticked yet, so heartbeats would
 * legitimately appear stale and fail every single deploy. Heartbeats are
 * for monitoring, not gating — pass `--include-heartbeats` to override.
 *
 * This command never gates on production infrastructure from CI — see
 * docs/TASK-system-health-checks.md §6.2 for why that would inspect the
 * wrong machine.
 */
class SystemCheckCommand extends Command
{
    protected $signature = 'system:check {--critical : Only critical failures matter, exit non-zero on any} {--include-heartbeats : Include Tier 2 heartbeat checks in the --critical gate (unsafe immediately after a deploy)}';

    protected $description = 'Run every health check and report status; --critical exits non-zero on failure, for use as a deploy gate.';

    /** @var array<int, class-string<Check>> */
    private const HEARTBEAT_CHECKS = [
        ScheduleCheck::class,
        QueueCheck::class,
        ExpiredReservationsAreBeingReleased::class,
        PendingPaymentsAreBeingVerified::class,
    ];

    public function handle(): int
    {
        $onlyCritical = (bool) $this->option('critical');
        $includeHeartbeats = (bool) $this->option('include-heartbeats');

        $anyCriticalFailing = false;

        foreach (Health::registeredChecks() as $check) {
            if (! $check->shouldRun()) {
                continue;
            }

            $isHeartbeat = in_array($check::class, self::HEARTBEAT_CHECKS, true);

            if ($onlyCritical && $isHeartbeat && ! $includeHeartbeats) {
                continue;
            }

            // A check throwing (a bug in the check itself, an unseeded
            // dependency, a DB connectivity blip) must never abort this
            // whole command — this is the deploy-gate command, so an
            // uncaught exception here would fail a deploy with a raw
            // stack trace instead of the actionable per-check report,
            // and skip every remaining check in both loops. Same
            // tolerance ListCriticalHealthFailures already applies for
            // the same reason on the admin bar's own health summary.
            try {
                $result = $check->run();
            } catch (Throwable $e) {
                $this->reportLine($check->getLabel(), 'crashed', $e->getMessage());
                $anyCriticalFailing = true;

                continue;
            }

            $isFailing = in_array($result->status, [Status::failed(), Status::crashed()], true);

            if ($onlyCritical && ! $isFailing) {
                continue;
            }

            $this->reportLine($check->getLabel(), (string) $result->status->value, $result->getNotificationMessage());

            if ($isFailing) {
                $anyCriticalFailing = true;
            }
        }

        foreach (IntegrityCheckResult::query()->orderBy('check_name')->get() as $integrityResult) {
            $isFailing = $integrityResult->status === 'failed';

            if ($onlyCritical && ! $isFailing) {
                continue;
            }

            $this->reportLine($integrityResult->check_name, $integrityResult->status, (string) $integrityResult->message);

            if ($isFailing) {
                $anyCriticalFailing = true;
            }
        }

        if (! $onlyCritical) {
            return self::SUCCESS;
        }

        if ($anyCriticalFailing) {
            $this->error('One or more critical checks are failing.');

            return self::FAILURE;
        }

        $this->info('All critical checks passed.');

        return self::SUCCESS;
    }

    private function reportLine(string $label, string $status, string $message): void
    {
        $line = "{$label}: {$status}".($message !== '' ? " — {$message}" : '');

        match ($status) {
            'ok' => $this->info($line),
            'warning' => $this->warn($line),
            default => $this->error($line),
        };
    }
}
