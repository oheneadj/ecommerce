<?php

/**
 * Sends an ad-hoc, staff-composed SMS to a customer.
 */

declare(strict_types=1);

namespace App\Actions\Customer;

use App\Exceptions\CustomerMissingContactMethodException;
use App\Models\User;
use App\Sms\Contracts\SmsGateway;
use Lorisleiva\Actions\Concerns\AsAction;

class SendSmsToCustomer
{
    use AsAction;

    public function __construct(private readonly SmsGateway $sms) {}

    /**
     * @throws CustomerMissingContactMethodException when the customer has no phone on file
     */
    public function handle(User $customer, string $message): void
    {
        if ($customer->phone === null) {
            throw new CustomerMissingContactMethodException("Customer #{$customer->id} has no phone on file.");
        }

        $this->sms->send($customer->phone, $message);
    }
}
