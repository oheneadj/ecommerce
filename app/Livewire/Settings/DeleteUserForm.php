<?php

namespace App\Livewire\Settings;

use App\Actions\Auth\DeleteAccount;
use App\Concerns\PasswordValidationRules;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class DeleteUserForm extends Component
{
    use PasswordValidationRules;

    public string $password = '';

    /**
     * Delete the currently authenticated user's account. The actual
     * deletion logic (soft delete + freeing the unique email/phone/
     * google_id for reuse) lives in DeleteAccount, not here — this
     * component is UI/HTTP glue only.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        $user = Auth::user();
        $logout();
        DeleteAccount::run($user);

        $this->redirect('/', navigate: true);
    }
}
