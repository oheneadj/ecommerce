<?php

/**
 * Staff compose-and-send page for broadcasting a message to customers.
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\Customer\BroadcastMessageToCustomers;
use App\Enums\CustomerSegment;
use App\Enums\UserRole;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * UI/targeting-resolution only, per CLAUDE.md §9 — the actual delivery
 * fan-out lives in `BroadcastMessageToCustomers`/`FanOutCustomerBroadcast`.
 * One message (subject + body) reused verbatim across every selected
 * channel: the email subject/body, the SMS text, and the in-app
 * notification title/body, rather than a separate rich-text email body
 * kept in sync with a separate SMS text.
 */
class SendCustomerNotification extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.send-customer-notification';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?string $title = 'Send Notification';

    protected static ?string $navigationLabel = 'Send Notification';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->getSchema('form')?->fill([
            'target' => 'all',
            'channels' => [],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Radio::make('target')
                    ->label('Send to')
                    ->options([
                        'all' => 'All customers',
                        'segment' => 'A segment',
                        'specific' => 'Specific customers',
                    ])
                    ->default('all')
                    ->live()
                    ->required(),

                Select::make('segment')
                    ->label('Segment')
                    ->options(collect(CustomerSegment::cases())->mapWithKeys(
                        fn (CustomerSegment $segment): array => [$segment->value => $segment->label()],
                    ))
                    ->visible(fn (Get $get): bool => $get('target') === 'segment')
                    ->required(fn (Get $get): bool => $get('target') === 'segment'),

                Select::make('customerIds')
                    ->label('Customers')
                    ->multiple()
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => User::query()
                        ->customers()
                        ->where(fn (Builder $query) => $query
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%"))
                        ->limit(50)
                        ->pluck('name', 'id')
                        ->all())
                    ->getOptionLabelsUsing(fn (array $values): array => User::query()
                        ->whereIn('id', $values)
                        ->pluck('name', 'id')
                        ->all())
                    ->visible(fn (Get $get): bool => $get('target') === 'specific')
                    ->required(fn (Get $get): bool => $get('target') === 'specific'),

                CheckboxList::make('channels')
                    ->label('Channels')
                    ->options([
                        'email' => 'Email',
                        'sms' => 'SMS',
                        'database' => 'In-app notification',
                    ])
                    ->required()
                    ->minItems(1),

                TextInput::make('subject')
                    ->required()
                    ->maxLength(255),

                Textarea::make('message')
                    ->required()
                    ->rows(6)
                    ->helperText('Sent as-is across every selected channel — the email body, the SMS text, and the in-app notification body.'),
            ])
            ->statePath('data');
    }

    /**
     * Resolves the chosen target into a recipient query, hands off to
     * BroadcastMessageToCustomers, and reports how many customers were
     * targeted — not "sent", since delivery itself happens async.
     */
    public function send(): void
    {
        $state = $this->getSchema('form')?->getState() ?? [];

        $count = BroadcastMessageToCustomers::run(
            $this->resolveRecipients($state),
            $state['subject'],
            $state['message'],
            $state['channels'] ?? [],
        );

        $notification = Notification::make()->title(
            $count > 0 ? "Queued for {$count} customer(s)" : 'No matching customers',
        );

        $count > 0 ? $notification->success() : $notification->warning();
        $notification->send();

        if ($count > 0) {
            $this->getSchema('form')?->fill(['target' => 'all', 'channels' => []]);
        }
    }

    /**
     * @param  array<string, mixed>  $state
     * @return Builder<User>
     */
    private function resolveRecipients(array $state): Builder
    {
        return match ($state['target'] ?? 'all') {
            'segment' => CustomerSegment::from($state['segment'])->apply(User::query()->customers()),
            'specific' => User::query()->customers()->whereIn('id', $state['customerIds'] ?? []),
            default => User::query()->customers(),
        };
    }

    /**
     * Same Admin/Super Admin scope as every other customer-communication
     * capability (UserPolicy::sendCommunication) — Store Keeper's role
     * never touches customers, per the BRD role table.
     */
    public static function canAccess(): bool
    {
        return Auth::user()?->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Admin->value]) ?? false;
    }
}
