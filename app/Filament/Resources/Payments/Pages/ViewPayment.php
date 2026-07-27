<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Payments\Schemas\PaymentInfolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

/**
 * Deliberately Super-Admin-only, narrower than PaymentPolicy's Admin+Super
 * Admin (which governs the list) — this page surfaces raw provider payload,
 * so it gets a stricter gate of its own rather than reusing the policy.
 */
class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    public function infolist(Schema $schema): Schema
    {
        return PaymentInfolist::configure($schema);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public static function canAccess(array $parameters = []): bool
    {
        return Auth::user()?->hasRole(UserRole::SuperAdmin->value) ?? false;
    }
}
