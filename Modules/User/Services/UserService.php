<?php

namespace Modules\User\Services;

use Modules\User\Models\User;
use Modules\User\Repositories\UserRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Webhooks\app\Events\UserDeleted;

class UserService
{
    protected $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Get all users with filtering, sorting, and pagination
     */
    public function getAllUsers(array $filters = [], array $sorts = [], array $includes = []): CursorPaginator
    {
        return $this->userRepository->getAllWithQueryBuilder($filters, $sorts, $includes);
    }

    /**
     * Find user by ID
     */
    public function findById(int $id): ?User
    {
        return $this->userRepository->findById($id);
    }

    /**
     * Find user by ID or fail
     */
    public function findByIdOrFail(int $id): User
    {
        return $this->userRepository->findByIdOrFail($id);
    }

    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?User
    {
        return $this->userRepository->findByEmail($email);
    }

    /**
     * Find user with roles
     */
    public function findWithRoles(int $id): ?User
    {
        return $this->userRepository->findWithRoles($id);
    }

    /**
     * Create a new user
     */
    public function createUser(array $data): User
    {
        // Check if email already exists
        if ($this->userRepository->emailExists($data['email'])) {
            throw ValidationException::withMessages([
                'email' => ['The email has already been taken.']
            ]);
        }

        // Hash password if provided
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        // Generate global_id for synced resources if not provided
        if (!isset($data['global_id'])) {
            $data['global_id'] = Str::uuid()->toString();
        }

        // Set default status if not provided
        if (!isset($data['status'])) {
            $data['status'] = 'active';
        }

        return $this->userRepository->create($data);
    }

    /**
     * Update user
     */
    public function updateUser(int $id, array $data): User
    {
        $user = $this->userRepository->findByIdOrFail($id);

        // Check if email already exists (excluding current user)
        if (isset($data['email']) && $this->userRepository->emailExists($data['email'], $id)) {
            throw ValidationException::withMessages([
                'email' => ['The email has already been taken.']
            ]);
        }

        // Hash password if provided
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        // Don't allow updating global_id as it's used for sync
        unset($data['global_id']);

        $this->userRepository->update($user, $data);
        
        return $user->fresh();
    }

    /**
     * Soft delete user
     */
    public function deleteUser(int $id): bool
    {
        $user = $this->userRepository->findByIdOrFail($id);
        
        $result = $this->userRepository->delete($user);
        
        if ($result) {
            // Fire webhook event
            event(new UserDeleted($id));
        }
        
        return $result;
    }

    /**
     * Restore soft-deleted user
     */
    public function restoreUser(int $id): bool
    {
        return $this->userRepository->restore($id);
    }

    /**
     * Force delete user (permanent)
     */
    public function forceDeleteUser(int $id): bool
    {
        $user = $this->userRepository->findByIdOrFail($id);
        return $this->userRepository->forceDelete($user);
    }

    /**
     * Update user status
     */
    public function updateStatus(int $id, string $status): User
    {
        $user = $this->userRepository->findByIdOrFail($id);
        $this->userRepository->update($user, ['status' => $status]);
        
        return $user->fresh();
    }

    /**
     * Change user password
     */
    public function changePassword(int $id, string $newPassword): bool
    {
        $user = $this->userRepository->findByIdOrFail($id);
        return $this->userRepository->update($user, [
            'password' => Hash::make($newPassword)
        ]);
    }

    /**
     * Search users
     */
    public function searchUsers(string $term): Collection
    {
        return $this->userRepository->search($term);
    }

    /**
     * Get users by status
     */
    public function getUsersByStatus(string $status): Collection
    {
        return $this->userRepository->getByStatus($status);
    }

    /**
     * Get trashed users
     */
    public function getTrashedUsers(): Collection
    {
        return $this->userRepository->getTrashed();
    }

    /**
     * Assign role to user
     */
    public function assignRole(int $userId, string $role): User
    {
        $user = $this->userRepository->findByIdOrFail($userId);
        $user->assignRole($role);
        
        return $user->fresh('roles');
    }

    /**
     * Remove role from user
     */
    public function removeRole(int $userId, string $role): User
    {
        $user = $this->userRepository->findByIdOrFail($userId);
        $user->removeRole($role);
        
        return $user->fresh('roles');
    }

    /**
     * Bulk user operations
     */
    public function bulkDelete(array $userIds): array
    {
        $results = [];
        foreach ($userIds as $id) {
            try {
                $results[$id] = $this->deleteUser($id);
            } catch (\Exception $e) {
                $results[$id] = false;
            }
        }
        return $results;
    }

    /**
     * Bulk status update
     */
    public function bulkUpdateStatus(array $userIds, string $status): array
    {
        $results = [];
        foreach ($userIds as $id) {
            try {
                $this->updateStatus($id, $status);
                $results[$id] = true;
            } catch (\Exception $e) {
                $results[$id] = false;
            }
        }
        return $results;
    }
}
