<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Webhook;
use App\Models\WebhookLog;
use App\Services\WebhookUrlValidator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class DispatchWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;
    public array $backoff = [30, 120, 600, 1800];
    public int $timeout = 30;

    public function __construct(
        private readonly int $businessId,
        private readonly string $event,
        private readonly array $payload,
    ) {
    }

    public function handle(WebhookUrlValidator $validator): void
    {
        $webhooks = Webhook::query()->forBusiness($this->businessId)
            ->where('event', $this->event)
            ->where('is_active', true)
            ->get();

        foreach ($webhooks as $webhook) {
            $log = WebhookLog::create([
                'webhook_id' => $webhook->id,
                'event' => $this->event,
                'payload' => $this->payload,
                'attempts' => $this->attempts(),
            ]);

            if (! $validator->isSafeHttpsUrl($webhook->url)) {
                $log->update(['failed_at' => now(), 'response_body' => 'Webhook URL failed outbound safety validation.']);
                $webhook->increment('failure_count');
                Log::warning('ShopLine rejected an unsafe webhook destination.', ['webhook_id' => $webhook->id]);
                continue;
            }

            $body = json_encode([
                'event' => $this->event,
                'created_at' => now('UTC')->toISOString(),
                'data' => $this->payload,
            ], JSON_THROW_ON_ERROR);
            $signature = hash_hmac('sha256', $body, (string) $webhook->secret);

            try {
                $response = Http::connectTimeout(5)
                    ->timeout(15)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'X-ShopLine-Event' => $this->event,
                        'X-ShopLine-Signature' => 'sha256='.$signature,
                        'X-ShopLine-Webhook-ID' => (string) $webhook->id,
                    ])
                    ->withBody($body, 'application/json')
                    ->post($webhook->url);

                $succeeded = $response->successful();
                $log->update([
                    'response_status' => $response->status(),
                    'response_body' => mb_substr($response->body(), 0, 20000),
                    'attempts' => $this->attempts(),
                    'succeeded_at' => $succeeded ? now() : null,
                    'failed_at' => $succeeded ? null : now(),
                ]);
                $webhook->forceFill([
                    'last_triggered_at' => now(),
                    'failure_count' => $succeeded ? 0 : $webhook->failure_count + 1,
                ])->save();
            } catch (Throwable $exception) {
                $log->update([
                    'response_body' => mb_substr($exception->getMessage(), 0, 20000),
                    'attempts' => $this->attempts(),
                    'failed_at' => now(),
                ]);
                $webhook->increment('failure_count');
                if ($exception instanceof ConnectionException) {
                    report($exception);
                }
            }
        }
    }
}
