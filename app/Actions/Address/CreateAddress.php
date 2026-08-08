<?php

/**
 * Saves a new address to a customer's account.
 */

declare(strict_types=1);

namespace App\Actions\Address;

use App\Models\Address;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A brand-new account's first saved address is always made the default —
 * an address book with a saved address but no default would leave
 * checkout with nothing to preselect.
 */
class CreateAddress
{
    use AsAction;

    /**
     * @param  array{label?: ?string, recipient_name: string, phone: string, line1: string, line2?: ?string, city: string, region?: ?string, is_default?: bool}  $data
     */
    public function handle(User $user, array $data): Address
    {
        $isFirstAddress = ! $user->addresses()->exists();

        return $user->addresses()->create([
            ...$data,
            'is_default' => ($data['is_default'] ?? false) || $isFirstAddress,
        ]);
    }
}
