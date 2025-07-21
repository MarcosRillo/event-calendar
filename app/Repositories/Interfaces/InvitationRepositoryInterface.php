<?php

namespace App\Repositories\Interfaces;

use App\Models\Invitation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface InvitationRepositoryInterface
{
    /**
     * Create a new invitation
     */
    public function create(array $data): Invitation;

    /**
     * Find invitation by token
     */
    public function findByToken(string $token): ?Invitation;

    /**
     * Find invitation by ID with relationships
     */
    public function findWithRelations(int $id, array $relations = []): ?Invitation;

    /**
     * Get paginated invitations with filters
     */
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Update invitation status
     */
    public function updateStatus(int $id, int $statusId, array $additionalData = []): bool;

    /**
     * Get expired invitations
     */
    public function getExpired(): Collection;

    /**
     * Get invitations by status
     */
    public function getByStatus(string $statusName): Collection;

    /**
     * Check if email has pending invitation
     */
    public function hasPendingInvitation(string $email): bool;

    /**
     * Delete invitation
     */
    public function delete(int $id): bool;

    /**
     * Get invitation statistics
     */
    public function getStatistics(): array;
}
