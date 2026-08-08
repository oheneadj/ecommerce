<?php

/**
 * Updates an existing saved address.
 */

declare(strict_types=1);

namespace App\Actions\Address;

use App\Models\Address;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateAddress
{
    use AsAction;

    /**
     * @param  array{label?: ?string, recipient_name: string, phone: string, line1: string, line2?: ?string, city: string, region?: ?string, is_default?: bool}  $data
     */
    public function handle(Address $address, array $data): Address
    {
        $address->update($data);

        return $address;
    }
}
