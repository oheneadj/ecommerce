<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How often automatic backups run, chosen by a Super Admin in Store
 * Settings — App\Actions\Backup\RunScheduledBackup reads this to decide
 * whether it's actually time to run, since the schedule itself always
 * ticks daily (routes/console.php) regardless of which frequency is set.
 */
enum BackupFrequency: string implements HasLabel
{
    case Daily = 'daily';
    case Weekly = 'weekly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    /**
     * The minimum gap, in days, that must have passed since the last
     * successful backup before another automatic one is due.
     */
    public function intervalInDays(): int
    {
        return match ($this) {
            self::Daily => 1,
            self::Weekly => 7,
        };
    }
}
