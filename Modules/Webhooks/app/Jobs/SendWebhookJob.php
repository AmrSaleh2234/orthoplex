<?php

namespace Modules\Webhooks\app\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Modules\Webhooks\app\Models\Webhook;

class SendWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $webhook;
    public $payload;

    public function __construct(Webhook $webhook, array $payload)
    {
        $this->webhook = $webhook;
        $this->payload = $payload;
    }

    public function handle(): void
    {
        $signature = $this->generateSignature();

        Http::withHeaders(['X-Webhook-Signature' => $signature])
            ->post($this->webhook->url, $this->payload);
    }

    private function generateSignature(): string
    {
        return hash_hmac('sha256', json_encode($this->payload), $this->webhook->secret);
    }
}
