<?php

/**
 * Super-Admin-only page for editing singleton store-wide configuration.
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\Catalog\ConvertImageToWebp;
use App\Enums\BackupFrequency;
use App\Enums\RemoteStorageProvider;
use App\Enums\SmsProvider;
use App\Enums\UserRole;
use App\Models\StoreSetting;
use App\Rules\PhoneNumber;
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
        'address_street',
        'address_city',
        'address_region',
        'address_postal_code',
        'address_country',
        'latitude',
        'longitude',
        'ga_measurement_id',
        'facebook_url',
        'instagram_url',
        'x_url',
        'tiktok_url',
        'whatsapp_url',
        'whatsapp_chat_enabled',
        'active_sms_provider',
        'active_remote_storage_provider',
        'backup_auto_enabled',
        'backup_frequency',
        'backup_retention_days',
        'tax_rate',
        'timezone',
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
                                    ->maxLength(255)
                                    // Normalizes on blur too, but that's
                                    // client-side and skippable —
                                    // dehydrateStateUsing() is the real
                                    // guarantee, running server-side on every
                                    // save regardless.
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (?string $state, callable $set) => $set('contact_phone', PhoneNumber::normalize((string) $state) ?? $state))
                                    ->dehydrateStateUsing(fn (?string $state): ?string => $state === null ? null : (PhoneNumber::normalize($state) ?? $state))
                                    ->rule(new PhoneNumber)
                                    ->placeholder('e.g. +233201234567 or 0201234567'),

                                TextInput::make('contact_address')
                                    ->maxLength(255)
                                    ->helperText('Free-text, shown on the storefront/receipts. Fill in the structured fields below too — they power the local-search (Google Maps) listing data.'),
                            ]),
                    ]),

                Section::make('Local SEO — structured address & location')
                    ->description('Powers the LocalBusiness structured data Google reads for Maps and local search results. The street and city are the minimum needed for that to render at all.')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('address_street')
                                    ->label('Street address')
                                    ->maxLength(255)
                                    ->placeholder('e.g. 12 Independence Ave'),

                                TextInput::make('address_city')
                                    ->label('City')
                                    ->maxLength(255)
                                    ->placeholder('e.g. Accra'),

                                TextInput::make('address_region')
                                    ->label('Region')
                                    ->maxLength(255)
                                    ->placeholder('e.g. Greater Accra'),

                                TextInput::make('address_postal_code')
                                    ->label('Postal / digital address code')
                                    ->maxLength(255)
                                    ->placeholder('e.g. GA-184-9821'),

                                TextInput::make('address_country')
                                    ->label('Country code')
                                    ->maxLength(2)
                                    ->helperText('ISO 3166-1 alpha-2, e.g. GH.'),

                                TextInput::make('latitude')
                                    ->numeric()
                                    ->step('0.0000001')
                                    ->placeholder('e.g. 5.6037168'),

                                TextInput::make('longitude')
                                    ->numeric()
                                    ->step('0.0000001')
                                    ->placeholder('e.g. -0.1869644'),
                            ]),
                    ]),

                Section::make('Google Analytics')
                    ->description('Adds the GA4 tracking snippet to every storefront page once a measurement ID is set. Leave blank to disable tracking entirely.')
                    ->schema([
                        TextInput::make('ga_measurement_id')
                            ->label('GA4 measurement ID')
                            ->maxLength(255)
                            ->placeholder('e.g. G-XXXXXXXXXX')
                            ->rule('nullable|regex:/^G-[A-Z0-9]+$/')
                            ->helperText('Found in Google Analytics under Admin → Data Streams → your web stream.'),
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

                Section::make('Backups')
                    ->description('Automatic and manual database + file backups run from Settings → Backups. This only configures the schedule — see that page for history and to run one now.')
                    ->schema([
                        Select::make('active_remote_storage_provider')
                            ->label('Remote storage provider')
                            ->options(RemoteStorageProvider::class)
                            ->native(false)
                            ->helperText('Credentials are set via environment variables (a Google Cloud service account) — this only chooses which already-configured destination is active.')
                            ->rule(fn () => function (string $attribute, mixed $value, Closure $fail): void {
                                if ($value !== null && ! RemoteStorageProvider::from($value)->hasCredentialsConfigured()) {
                                    $fail('This provider has no credentials configured in the environment yet.');
                                }
                            }),

                        Grid::make(3)
                            ->schema([
                                Toggle::make('backup_auto_enabled')
                                    ->label('Run backups automatically'),

                                Select::make('backup_frequency')
                                    ->options(BackupFrequency::class)
                                    ->native(false)
                                    ->requiredIf('backup_auto_enabled', true),

                                TextInput::make('backup_retention_days')
                                    ->label('Retention (days)')
                                    ->helperText('30 is this project\'s own documented minimum.')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1),
                            ]),
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

                                Select::make('timezone')
                                    ->label('Display timezone')
                                    ->helperText('Everything is stored in UTC — this only controls what customers see on order confirmations, order history, and invoices.')
                                    ->options(array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                                    ->searchable()
                                    ->required(),

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
