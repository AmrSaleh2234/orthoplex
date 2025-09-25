<?php

namespace Modules\Webhooks\app\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Tenant\Services\TenantService;
use Modules\User\Models\User;
use Modules\Webhooks\app\Events\UserCreated;

class ProvisionTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function handle(TenantService $tenantService): void
    {
        // 1. Create the tenant and their database
        $tenant = $tenantService->create([
            'name' => $this->data['name'],
            'domain' => $this->data['domain'],
        ]);

        // 2. Run the creation logic within the tenant's context
        $tenant->run(function () {
            // 3. Create the owner user
            $user = User::create([
                'name' => $this->data['owner_name'],
                'email' => $this->data['owner_email'],
                'password' => bcrypt($this->data['owner_password']),
                'email_verified_at' => now(),
            ]);

            // 4. Assign the 'owner' role
            $user->assignRole('owner');

            // 5. Fire the UserCreated event
            event(new UserCreated($user));
        });
    }
}
