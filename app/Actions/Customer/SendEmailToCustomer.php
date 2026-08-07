<?php

/**
 * Sends an ad-hoc, staff-composed email to a customer.
 */

declare(strict_types=1);

namespace App\Actions\Customer;

use App\Exceptions\CustomerMissingContactMethodException;
use App\Jobs\SendCustomerEmail;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The has-an-email check runs synchronously so the admin gets immediate
 * feedback, but the actual SMTP call is dispatched to SendCustomerEmail —
 * per this project's "external API calls must be queued" convention. A
 * plain Mailable — not a Notification, since this is a one-off message
 * staff composes on the spot (subject/body), not a reaction to a business
 * event that already has its own Notification class.
 */
class SendEmailToCustomer
{
    use AsAction;

    /**
     * @throws CustomerMissingContactMethodException when the customer has no email on file
     */
    public function handle(User $customer, string $subject, string $body): void
    {
        if ($customer->email === null) {
            throw new CustomerMissingContactMethodException("Customer #{$customer->id} has no email on file.");
        }

        SendCustomerEmail::dispatch($customer->id, $subject, $body);
    }
}
