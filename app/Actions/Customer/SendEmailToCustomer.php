<?php

/**
 * Sends an ad-hoc, staff-composed email to a customer.
 */

declare(strict_types=1);

namespace App\Actions\Customer;

use App\Exceptions\CustomerMissingContactMethodException;
use App\Mail\CustomerMessage;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A plain Mailable — not a Notification, since this is a one-off message
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

        Mail::to($customer->email)->send(new CustomerMessage($subject, $body));
    }
}
