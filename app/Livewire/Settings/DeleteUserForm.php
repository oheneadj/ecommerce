<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Actions\Auth\DeleteAccount;
use App\Concerns\PasswordValidationRules;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Storefront settings form for a customer to delete their own account.
 */
class DeleteUserForm extends Component
{
    use PasswordValidationRules;

    public string $password = '';

    /**
     * A phone-OTP-only or Google-only customer never has a password
     * (users.password is null) — Laravel's `current_password` rule always
     * fails against a null hash, which used to make self-service deletion
     * permanently unreachable for that account. The already-authenticated
     * session is itself the confirmation for those accounts; only an
     * account that actually has a password needs to re-enter it here.
     */
    #[Computed]
    public function hasPassword(): bool
    {
        return Auth::user()?->password !== null;
    }

    /**
     * Delete the currently authenticated user's account. The actual
     * deletion logic (soft delete + freeing the unique email/phone/
     * google_id for reuse) lives in DeleteAccount, not here — this
     * component is UI/HTTP glue only.
     */
    public function deleteUser(Logout $logout): void
    {
        if ($this->hasPassword()) {
            $this->validate([
                'password' => $this->currentPasswordRules(),
            ]);
        }

        $user = Auth::user();
        $logout();
        DeleteAccount::run($user);

        $this->redirect('/', navigate: true);
    }
}
