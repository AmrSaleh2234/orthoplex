<?php

namespace Modules\Tenant\Repositories;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Tenant\Models\Tenant;

class TenantRepository
{
    public function create(array $data): Tenant
    {
        return Tenant::create($data);
    }

    public function find(string $id, bool $lockForUpdate = false): ?Tenant
    {
        $query = Tenant::query();

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->find($id);
    }

    public function update(string $id, array $data): Tenant
    {
        $tenant = $this->find($id, true);

        if (! $tenant) {
            throw new ModelNotFoundException('Tenant not found.');
        }

        $tenant->update($data);

        return $tenant;
    }

    public function delete(string $id): bool
    {
        $tenant = $this->find($id);

        if (! $tenant) {
            throw new ModelNotFoundException('Tenant not found.');
        }

        return $tenant->delete();
    }
}
