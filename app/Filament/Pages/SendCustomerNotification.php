<?php

/**
 * Staff compose-and-send page for broadcasting a message to customers.
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\Customer\BroadcastMessageToCustomers;
use App\Enums\CustomerSegment;
use App\Enums\UserRole;
use App\Exceptions\BroadcastRateLimitedException;
use App\Exceptions\BroadcastRecipientLimitExceededException;
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
use Filament\Schemas\Components\Section;
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
                Section::make('Recipients')
                    ->description('Choose who this notification reaches. Staff accounts are never included, regardless of target.')
                    ->schema([
                        Radio::make('target')
                            ->label('Send to')
                            ->options([
                                'all' => 'All customers',
                                'segment' => 'A segment',
                                'specific' => 'Specific customers',
                            ])
                            ->default('all')
                            ->live()
                            ->required()
                            ->columnSpanFull(),

                        Select::make('segment')
                            ->label('Segment')
                            ->options(collect(CustomerSegment::cases())->mapWithKeys(
                                fn (CustomerSegment $segment): array => [$segment->value => $segment->label()],
                            ))
                            ->helperText('Recalculated at send time — always reflects who currently matches.')
                            ->visible(fn (Get $get): bool => $get('target') === 'segment')
                            ->required(fn (Get $get): bool => $get('target') === 'segment'),

                        Select::make('customerIds')
                            ->label('Customers')
                            ->multiple()
                            ->searchable()
                            ->placeholder('Search by name, email, or phone…')
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
                    ]),

                Section::make('Channels')
                    ->description('At least one is required. A customer missing the contact method for a channel is skipped for that channel only.')
                    ->schema([
                        CheckboxList::make('channels')
                            ->label('Channels')
                            ->hiddenLabel()
                            ->options([
                                'email' => 'Email',
                                'sms' => 'SMS',
                                'database' => 'In-app notification',
                            ])
                            ->descriptions([
                                'email' => 'Sent to the customer\'s email on file.',
                                'sms' => 'Sent to the customer\'s phone on file.',
                                'database' => 'Shown on the storefront notification bell and account page.',
                            ])
                            ->required()
                            ->minItems(1),
                    ]),

                Section::make('Message')
                    ->description('One message, reused as-is across every selected channel.')
                    ->schema([
                        TextInput::make('subject')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. Weekend sale — 20% off everything')
                            ->helperText('Used as the email subject and the in-app notification title.')
                            ->columnSpanFull(),

                        Textarea::make('message')
                            ->required()
                            ->rows(6)
                            ->placeholder('Write the message customers will see…')
                            ->helperText('Used as the email body, the SMS text, and the in-app notification body — plain text, no formatting.')
                            ->columnSpanFull(),
                    ]),
            ])
            ->columns(1)
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

        try {
            $count = BroadcastMessageToCustomers::run(
                $this->resolveRecipients($state),
                $state['subject'],
                $state['message'],
                $state['channels'] ?? [],
                Auth::id(),
            );
        } catch (BroadcastRecipientLimitExceededException|BroadcastRateLimitedException $e) {
            Notification::make()->title('Broadcast not sent')->body($e->getMessage())->danger()->send();

            return;
        }

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
