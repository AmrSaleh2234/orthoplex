<?php

namespace Modules\User\Repositories;

use Modules\User\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Spatie\QueryBuilder\QueryBuilder;

class UserRepository
{
    /**
     * Get all users with optional filtering, sorting, and includes
     */
    public function getAllWithQueryBuilder(array $filters = [], array $sorts = [], array $includes = []): CursorPaginator
    {
        return QueryBuilder::for(User::class)
            ->allowedFilters($filters ?: ['name', 'email', 'status'])
            ->allowedSorts($sorts ?: ['name', 'email', 'created_at'])
            ->allowedIncludes($includes ?: ['roles'])
            ->cursorPaginate();
    }

    /**
     * Find user by ID
     */
    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    /**
     * Find user by ID or fail
     */
    public function findByIdOrFail(int $id): User
    {
        return User::findOrFail($id);
    }

    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    /**
     * Find user by global_id (for synced resources)
     */
    public function findByGlobalId(string $globalId): ?User
    {
        return User::where('global_id', $globalId)->first();
    }

    /**
     * Create a new user
     */
    public function create(array $data): User
    {
        return User::create($data);
    }

    /**
     * Update user
     */
    public function update(User $user, array $data): bool
    {
        return $user->update($data);
    }

    /**
     * Soft delete user
     */
    public function delete(User $user): bool
    {
        return $user->delete();
    }

    /**
     * Force delete user
     */
    public function forceDelete(User $user): bool
    {
        return $user->forceDelete();
    }

    /**
     * Restore soft-deleted user
     */
    public function restore(int $id): bool
    {
        $user = User::withTrashed()->findOrFail($id);
        return $user->restore();
    }

    /**
     * Get user with roles
     */
    public function findWithRoles(int $id): ?User
    {
        return User::with('roles')->find($id);
    }

    /**
     * Get users by status
     */
    public function getByStatus(string $status): Collection
    {
        return User::where('status', $status)->get();
    }

    /**
     * Search users by name or email
     */
    public function search(string $term): Collection
    {
        return User::where('name', 'LIKE', "%{$term}%")
            ->orWhere('email', 'LIKE', "%{$term}%")
            ->get();
    }

    /**
     * Get trashed users
     */
    public function getTrashed(): Collection
    {
        return User::onlyTrashed()->get();
    }

    /**
     * Check if email exists
     */
    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $query = User::where('email', $email);
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        
        return $query->exists();
    }
}
