<?php

namespace Modules\Tenant\Services;

use Illuminate\Support\Facades\DB;
use Modules\Tenant\app\Exceptions\ConcurrencyException;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Repositories\TenantRepository;

class TenantService
{
    protected $tenantRepository;

    public function __construct(TenantRepository $tenantRepository)
    {
        $this->tenantRepository = $tenantRepository;
    }

    public function create(array $data): Tenant
    {
        $tenant = $this->tenantRepository->create($data);
        $tenant->domains()->create(['domain' => $data['domain']]);

        return $tenant;
    }

    public function find(string $id): ?Tenant
    {
        return $this->tenantRepository->find($id);
    }

    public function update(string $id, array $data): Tenant
    {
        return DB::transaction(function () use ($id, $data) {
            $tenant = $this->tenantRepository->find($id, true); // Lock for update

            if ($tenant->version != $data['version']) {
                throw new ConcurrencyException();
            }

            $data['version'] = $tenant->version + 1;

            return $this->tenantRepository->update($id, $data);
        });
    }

    public function delete(string $id): bool
    {
        return $this->tenantRepository->delete($id);
    }
}
