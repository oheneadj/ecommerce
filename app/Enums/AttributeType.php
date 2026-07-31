<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How a global product attribute's terms are presented and captured —
 * plain text, a color swatch (hex), or an image swatch.
 */
enum AttributeType: string implements HasLabel
{
    case Text = 'text';
    case Color = 'color';
    case Image = 'image';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::Color => 'Color swatch',
            self::Image => 'Image swatch',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
