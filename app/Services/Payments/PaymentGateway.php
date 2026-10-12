<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;

interface PaymentGateway
{
    /**
     * Gateway adapters must verify signatures and idempotency before changing local payment state.
     * This contract intentionally has no Stripe/Cashier dependency.
     *
     * @return array{status: string, transaction_id: ?string, response: array}
     */
    public function authorize(Order $order, Payment $payment, array $context = []): array;

    /** @return array{status: string, refund_id: ?string, response: array} */
    public function refund(Payment $payment, float $amount, array $context = []): array;
}
