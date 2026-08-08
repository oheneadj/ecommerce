<?php

/**
 * Super-Admin-only page for editing singleton store-wide configuration.
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\Catalog\ConvertImageToWebp;
use App\Enums\UserRole;
use App\Models\StoreSetting;
use BackedEnum;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Covers Epic E13's Store Settings & Branding fields (business name, logo,
 * brand colors, tagline, contact details, tax rate) alongside the existing
 * operational settings (stock reservation window, low-stock threshold).
 * Branding fields exist so the storefront/PDF receipts can be reskinned
 * per business deployment with no code change (E13.2) — nothing here is
 * ever hardcoded in a Blade view/PDF template.
 */
class ManageStoreSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.manage-store-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    private const FIELDS = [
        'business_name',
        'logo_path',
        'primary_color',
        'secondary_color',
        'tagline',
        'contact_email',
        'contact_phone',
        'contact_address',
        'tax_rate',
        'stock_reservation_minutes',
        'low_stock_threshold',
    ];

    public function mount(): void
    {
        $this->getSchema('form')?->fill(StoreSetting::current()->only(self::FIELDS));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Branding')
                    ->description('Reflected across the storefront and PDF receipts — no code change needed to reskin this deployment for a different business.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('business_name')
                                    ->maxLength(255)
                                    ->placeholder('e.g. Kente & Co'),

                                TextInput::make('tagline')
                                    ->maxLength(255)
                                    ->placeholder('e.g. Authentic Ghanaian fashion'),

                                ColorPicker::make('primary_color'),

                                ColorPicker::make('secondary_color'),
                            ]),

                        FileUpload::make('logo_path')
                            ->image()
                            ->maxSize(config('media.max_upload_size_kb'))
                            ->disk('public')
                            ->directory('branding')
                            ->saveUploadedFileUsing(ConvertImageToWebp::forFileUpload())
                            ->helperText('Displayed on the storefront and PDF receipts.'),
                    ]),

                Section::make('Contact details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('contact_email')
                                    ->email()
                                    ->maxLength(255),

                                TextInput::make('contact_phone')
                                    ->tel()
                                    ->maxLength(255),

                                TextInput::make('contact_address')
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Section::make('Checkout & inventory')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('tax_rate')
                                    ->label('Tax rate (%)')
                                    ->helperText('Applied uniformly to every order\'s subtotal. Whole numbers only, e.g. 15 for 15% VAT.')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0)
                                    ->maxValue(100),

                                TextInput::make('stock_reservation_minutes')
                                    ->label('Stock reservation window (minutes)')
                                    ->helperText('How long stock is held for an order awaiting payment before it is automatically released.')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1),

                                TextInput::make('low_stock_threshold')
                                    ->label('Low-stock alert threshold (default)')
                                    ->helperText('Store Keeper is alerted when a variant\'s stock falls to or below this. Overridable per variant.')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0),
                            ]),
                    ]),
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
