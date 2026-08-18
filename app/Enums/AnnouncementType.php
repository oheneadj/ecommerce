<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How an Announcement is displayed on the storefront.
 */
enum AnnouncementType: string implements HasLabel
{
    case Banner = 'banner';
    case Popup = 'popup';

    public function label(): string
    {
        return match ($this) {
            self::Banner => 'Banner (top of page, not dismissible)',
            self::Popup => 'Popup (modal, dismissible)',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
