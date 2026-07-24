<?php

/**
 * Super-Admin-only page for editing singleton store-wide configuration.
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\StoreSetting;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * Currently exposes only `stock_reservation_minutes` (BRD E3.2 — the
 * checkout reservation window must be admin-editable without a deploy).
 * Grows to cover branding/business details in later sprints.
 */
class ManageStoreSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.manage-store-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->getSchema('form')?->fill(StoreSetting::current()->only(['stock_reservation_minutes']));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('stock_reservation_minutes')
                    ->label('Stock reservation window (minutes)')
                    ->helperText('How long stock is held for an order awaiting payment before it is automatically released.')
                    ->numeric()
                    ->required()
                    ->minValue(1),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        StoreSetting::current()->update($this->getSchema('form')?->getState() ?? []);

        Notification::make()
            ->title('Store settings saved')
            ->success()
            ->send();
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(UserRole::SuperAdmin->value) ?? false;
    }
}
