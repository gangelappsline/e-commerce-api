<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\AbandonedCartMail;
use App\Models\AbandonedCart;
use App\Models\Cart;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

final class ExpireCarts extends Command
{
    protected $signature = 'shopline:carts:expire';

    protected $description = 'Archive expired carts and queue recovery reminders for known customers.';

    public function handle(): int
    {
        Cart::query()->whereNotNull('expires_at')->where('expires_at', '<=', now())
            ->with(['items.product', 'items.variant', 'customer.user', 'business'])
            ->chunkById(100, function ($carts): void {
                foreach ($carts as $cart) {
                    $email = $cart->customer?->user?->email;
                    $snapshot = $cart->items->map(fn ($item): array => [
                        'product_id' => $item->product_id,
                        'product_name' => $item->product?->name,
                        'variant_name' => $item->variant?->name,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'currency' => $cart->currency,
                    ])->all();
                    AbandonedCart::create([
                        'business_id' => $cart->business_id,
                        'customer_id' => $cart->customer_id,
                        'cart_snapshot' => ['items' => $snapshot, 'grand_total' => $cart->grand_total],
                        'email' => $email,
                        'reminder_sent_at' => $email !== null ? now() : null,
                    ]);
                    if (is_string($email) && $email !== '') {
                        Mail::to($email)->queue(new AbandonedCartMail([
                            'name' => $cart->customer?->user?->name,
                            'store_name' => $cart->business?->name,
                            'url' => rtrim((string) config('app.url'), '/').'/api/v1/cart',
                        ]));
                    }
                    $cart->delete();
                }
            });

        return self::SUCCESS;
    }
}
