<?php

namespace App\Checkout;

use App\Checkout\Model\Customer;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class PaymentGateway
{
    public function __construct(
        #[Target('payments.client')]
        private HttpClientInterface $payments,
    ) {
    }

    public function charge(Customer $customer, int $amountCents): string
    {
        $response = $this->payments->request('POST', '/charges', ['json' => ['customerId' => $customer->id, 'amountCents' => $amountCents]]);
        $result = $response->toArray(false);

        if (!$result['approved']) {
            throw new CheckoutFailed('payment_declined', \sprintf('Card was declined (%s) for %d cents.', $result['declineCode'], $amountCents));
        }

        return $result['paymentId'];
    }
}
