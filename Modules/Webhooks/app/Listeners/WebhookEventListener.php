<?php

namespace Modules\Webhooks\app\Listeners;

use Illuminate\Events\Dispatcher;
use Modules\Webhooks\app\Events\UserCreated;
use Modules\Webhooks\app\Events\UserDeleted;
use Modules\Webhooks\app\Jobs\SendWebhookJob;
use Modules\Webhooks\app\Models\Webhook;

class WebhookEventListener
{
    public function handleUserCreated(UserCreated $event): void
    {
        $this->sendWebhook('user.created', [
            'event' => 'user.created',
            'data' => $event->user->toArray(),
        ]);
    }

    public function handleUserDeleted(UserDeleted $event): void
    {
        $this->sendWebhook('user.deleted', [
            'event' => 'user.deleted',
            'data' => ['id' => $event->userId],
        ]);
    }

    protected function sendWebhook(string $eventName, array $payload): void
    {
        $webhooks = Webhook::where('status', 'active')
            ->whereJsonContains('events', $eventName)
            ->get();

        foreach ($webhooks as $webhook) {
            SendWebhookJob::dispatch($webhook, $payload);
        }
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            UserCreated::class,
            [self::class, 'handleUserCreated']
        );

        $events->listen(
            UserDeleted::class,
            [self::class, 'handleUserDeleted']
        );
    }
}
