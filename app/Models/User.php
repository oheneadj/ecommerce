<?php

/**
 * The application's user model.
 */

declare(strict_types=1);

namespace App\Models;

// Deliberately the trait (working hasVerifiedEmail()/sendEmailVerificationNotification()
// methods), never the Illuminate\Contracts\Auth\MustVerifyEmail interface — implementing
// the interface would flip the `verified` route middleware already applied to every
// account route from its current no-op into active enforcement, blocking a phone-only
// or otherwise-unverified customer from their own account. Verification here is a trust
// signal for specific decisions (Google auto-link, enabling password login), never a
// general access gate.
use App\Concerns\LogsAdminActivity;
use App\Enums\UserRole;
use App\Notifications\QueuedVerifyEmail;
use App\Observers\UserObserver;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Jeffgreco13\FilamentBreezy\Traits\TwoFactorAuthenticatable as BreezyTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

/**
 * A staff member or customer account. Staff (Super Admin/Admin/Store Keeper) authenticate
 * via Filament with roles from Spatie Permission; customers never hold any of these roles.
 *
 * @property int $id
 * @property string|null $name
 * @property string|null $phone
 * @property Carbon|null $phone_verified_at
 * @property string|null $email
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $google_id
 * @property string|null $avatar_url
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $disabled_at
 */
#[Fillable(['name', 'phone', 'email', 'password', 'google_id', 'avatar_url', 'disabled_at'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
#[ObservedBy(UserObserver::class)]
class User extends Authenticatable implements FilamentUser, HasAvatar, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use BreezyTwoFactorAuthenticatable, HasFactory, HasRoles, LogsAdminActivity, MustVerifyEmail, Notifiable, PasskeyAuthenticatable, SoftDeletes, TwoFactorAuthenticatable;

    /**
     * Overrides `LogsAdminActivity`'s default (log every fillable
     * attribute) — a customer editing their own name/email/phone via
     * account settings isn't "significant administrative action" (BRD
     * FR-10.2), only a staff account's disabled/enabled state is. Spatie
     * Activitylog resolves the causer from the current auth guard
     * automatically, so disabling/enabling via the Staff admin resource
     * gets attributed to the acting Super Admin with no extra code.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['disabled_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('User');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'disabled_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * The admin panel's avatar, uploaded via the Filament Breezy profile
     * page and stored on the public disk — never a live-fetched external
     * URL, so it keeps working even if the account's email changes.
     */
    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar_url ? Storage::disk('public')->url($this->avatar_url) : null;
    }

    /**
     * Staff-only gate for the Filament admin panel — customers never hold
     * any of these roles, per BRD Section 3. A disabled staff account is
     * blocked here too, even if it somehow still holds a valid session or
     * completes a pending password reset — disabling is meant to cut off
     * access immediately, not just prevent future logins.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->disabled_at === null && $this->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::Admin->value,
            UserRole::StoreKeeper->value,
        ]);
    }

    /**
     * Get the user's initials. Falls back to "?" for accounts created via OTP
     * login before a name has ever been provided.
     */
    public function initials(): string
    {
        if (blank($this->name)) {
            return '?';
        }

        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    /**
     * Route "sms" channel notifications to this account's phone number, if any.
     */
    public function routeNotificationForSms(): ?string
    {
        return $this->phone;
    }

    /**
     * Whether this account has an email that's been independently confirmed
     * as belonging to whoever controls it — either Google's own OAuth
     * verification (set at signup) or clicking the emailed verification
     * link. Distinct from the trait's own `hasVerifiedEmail()`: that only
     * checks `email_verified_at`, which says nothing about whether an
     * email even exists — a phone-only account has neither.
     */
    public function hasVerifiedEmailAddress(): bool
    {
        return $this->email !== null && $this->email_verified_at !== null;
    }

    /**
     * Whether this account has an email on file that still needs
     * verifying — used to decide whether to show a "verify your email"
     * reminder. False for an account with no email at all, since there's
     * nothing to nag about.
     */
    public function hasUnverifiedEmailAddress(): bool
    {
        return $this->email !== null && $this->email_verified_at === null;
    }

    /**
     * Overrides `MustVerifyEmail`'s default, which sends the framework's
     * own `VerifyEmail` notification — that class doesn't implement
     * `ShouldQueue`, so it blocked registration (and the resend button) on
     * the mail transport's full round trip, unlike every other external
     * call in this app. `QueuedVerifyEmail` is otherwise identical.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new QueuedVerifyEmail);
    }

    /**
     * Accounts holding none of the staff roles — the single definition of
     * "customer" reused everywhere that distinction matters (the Customers
     * admin resource, customer-broadcast targeting), instead of each call
     * site re-writing its own `whereDoesntHave('roles')`.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeCustomers(Builder $query): Builder
    {
        return $query->whereDoesntHave('roles');
    }

    /**
     * Accounts holding exactly the two roles manageable from the Staff
     * admin resource — deliberately not "has any role" the way
     * `scopeCustomers()` is the inverse of. Super Admin must never
     * resolve through this scope, since `StaffResource::getEloquentQuery()`
     * uses it to gate every route that resource has (list/create/edit),
     * not just the table display — a Super Admin account must 404 there
     * even via a hand-edited URL, not just be hidden from the list.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeStaff(Builder $query): Builder
    {
        return $query->whereHas('roles', fn ($rolesQuery) => $rolesQuery->whereIn('name', [
            UserRole::Admin->value,
            UserRole::StoreKeeper->value,
        ]));
    }

    /**
     * This account's past orders.
     *
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Reviews this account has written.
     *
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Addresses saved to this account (not just ones used on past orders).
     *
     * @return HasMany<Address, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * Variants this account has wishlisted.
     *
     * @return HasMany<WishlistItem, $this>
     */
    public function wishlistItems(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }
}
