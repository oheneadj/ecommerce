<?php

/**
 * Super-Admin-only page for editing singleton store-wide configuration.
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\Catalog\ConvertImageToWebp;
use App\Enums\SmsProvider;
use App\Enums\UserRole;
use App\Models\StoreSetting;
use BackedEnum;
use Closure;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
        'facebook_url',
        'instagram_url',
        'x_url',
        'tiktok_url',
        'whatsapp_url',
        'whatsapp_chat_enabled',
        'active_sms_provider',
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
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                null,
                                '1:1',
                                '16:9',
                                '4:3',
                            ])
                            ->maxSize(config('media.max_upload_size_kb'))
                            ->disk('public')
                            ->directory('branding')
                            ->saveUploadedFileUsing(ConvertImageToWebp::forFileUpload())
                            ->helperText('Displayed on the storefront and PDF receipts. Crop or adjust before saving.'),
                    ]),

                Section::make('Contact details')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('contact_email')
                                    ->email()
                                    ->maxLength(255),

                                TextInput::make('contact_phone')
                                    ->tel()
                                    ->maxLength(255),

                                TextInput::make('contact_address')
                                    ->maxLength(255),
                            ]),
                    ]),

                Section::make('Social media')
                    ->description('Shown as icon links in the storefront footer. Leave any blank to hide it — only the ones you fill in appear.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('facebook_url')
                                    ->label('Facebook')
                                    ->url()
                                    ->maxLength(255)
                                    ->placeholder('https://facebook.com/yourstore'),

                                TextInput::make('instagram_url')
                                    ->label('Instagram')
                                    ->url()
                                    ->maxLength(255)
                                    ->placeholder('https://instagram.com/yourstore'),

                                TextInput::make('x_url')
                                    ->label('X (Twitter)')
                                    ->url()
                                    ->maxLength(255)
                                    ->placeholder('https://x.com/yourstore'),

                                TextInput::make('tiktok_url')
                                    ->label('TikTok')
                                    ->url()
                                    ->maxLength(255)
                                    ->placeholder('https://tiktok.com/@yourstore'),

                                TextInput::make('whatsapp_url')
                                    ->label('WhatsApp')
                                    ->url()
                                    ->maxLength(255)
                                    ->placeholder('https://wa.me/233200000000')
                                    ->helperText('A wa.me link, not a phone number.'),
                            ]),
                    ]),

                Section::make('WhatsApp chat bubble')
                    ->description('Adds a floating WhatsApp button to every storefront page, linking straight to the wa.me link set above.')
                    ->schema([
                        Toggle::make('whatsapp_chat_enabled')
                            ->label('Show the chat bubble on the storefront')
                            ->helperText('Has no effect until a WhatsApp link is set above.'),
                    ]),

                Section::make('SMS provider')
                    ->description('Credentials are set via environment variables — this only chooses which already-configured provider is active. Payment providers are managed from Settings → Payment Providers instead, where more than one can be enabled at once for the customer to choose between at checkout.')
                    ->schema([
                        Select::make('active_sms_provider')
                            ->label('Active SMS provider')
                            ->options(SmsProvider::class)
                            ->native(false)
                            ->helperText('Used for OTP codes, low-stock/health alerts, and staff invites.')
                            ->rule(fn () => function (string $attribute, mixed $value, Closure $fail): void {
                                if ($value !== null && ! SmsProvider::from($value)->hasCredentialsConfigured()) {
                                    $fail('This provider has no credentials configured in the environment yet.');
                                }
                            }),
                    ]),

                Section::make('Checkout & inventory')
                    ->schema([
                        Grid::make(3)
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
