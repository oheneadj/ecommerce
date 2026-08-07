<?php

/**
 * Sends an ad-hoc, staff-composed SMS to a customer.
 */

declare(strict_types=1);

namespace App\Actions\Customer;

use App\Exceptions\CustomerMissingContactMethodException;
use App\Jobs\SendCustomerSms;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The has-a-phone check runs synchronously so the admin gets immediate
 * feedback, but the actual gateway call is dispatched to SendCustomerSms —
 * per this project's "external API calls must be queued" convention.
 */
class SendSmsToCustomer
{
    use AsAction;

    /**
     * @throws CustomerMissingContactMethodException when the customer has no phone on file
     */
    public function handle(User $customer, string $message): void
    {
        if ($customer->phone === null) {
            throw new CustomerMissingContactMethodException("Customer #{$customer->id} has no phone on file.");
        }

        SendCustomerSms::dispatch($customer->id, $message);
    }
}
