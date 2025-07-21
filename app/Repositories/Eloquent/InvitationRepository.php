<?php

namespace App\Repositories\Eloquent;

use App\Models\Invitation;
use App\Models\InvitationStatus;
use App\Repositories\Interfaces\InvitationRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class InvitationRepository implements InvitationRepositoryInterface
{
    protected $model;

    public function __construct(Invitation $model)
    {
        $this->model = $model;
    }

    /**
     * Create a new invitation
     */
    public function create(array $data): Invitation
    {
        return $this->model->create($data);
    }

    /**
     * Find invitation by token
     */
    public function findByToken(string $token): ?Invitation
    {
        return $this->model->where('token', $token)
            ->with(['status', 'organizationData', 'adminData'])
            ->first();
    }

    /**
     * Find invitation by ID with relationships
     */
    public function findWithRelations(int $id, array $relations = []): ?Invitation
    {
        $defaultRelations = [
            'status',
            'organizationData',
            'adminData',
            'createdBy:id,first_name,last_name,email',
            'updatedBy:id,first_name,last_name,email'
        ];

        $relations = !empty($relations) ? $relations : $defaultRelations;

        return $this->model->with($relations)->find($id);
    }

    /**
     * Get paginated invitations with filters
     */
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->with([
            'status',
            'organizationData',
            'adminData',
            'createdBy:id,first_name,last_name,email',
            'updatedBy:id,first_name,last_name,email'
        ]);

        // Apply status filter
        if (!empty($filters['status'])) {
            $statuses = is_array($filters['status']) ? $filters['status'] : [$filters['status']];
            $statusIds = InvitationStatus::whereIn('name', $statuses)->pluck('id');
            $query->whereIn('status_id', $statusIds);
        }

        // Apply search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhereHas('organizationData', function ($orgQ) use ($search) {
                      $orgQ->where('name', 'like', "%{$search}%")
                           ->orWhere('slug', 'like', "%{$search}%");
                  })
                  ->orWhereHas('adminData', function ($adminQ) use ($search) {
                      $adminQ->where('first_name', 'like', "%{$search}%")
                             ->orWhere('last_name', 'like', "%{$search}%")
                             ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // Apply date range filter
        if (!empty($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }

        // Apply ordering
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        $query->orderBy($sortBy, $sortDirection);

        return $query->paginate($perPage);
    }

    /**
     * Update invitation status
     */
    public function updateStatus(int $id, int $statusId, array $additionalData = []): bool
    {
        $updateData = array_merge(['status_id' => $statusId], $additionalData);
        
        return $this->model->where('id', $id)->update($updateData) > 0;
    }

    /**
     * Get expired invitations
     */
    public function getExpired(): Collection
    {
        return $this->model->where('expires_at', '<', now())
            ->whereHas('status', function ($query) {
                $query->whereIn('name', ['sent', 'pending']);
            })
            ->with(['status', 'organizationData', 'adminData'])
            ->get();
    }

    /**
     * Get invitations by status
     */
    public function getByStatus(string $statusName): Collection
    {
        return $this->model->whereHas('status', function ($query) use ($statusName) {
            $query->where('name', $statusName);
        })
        ->with(['status', 'organizationData', 'adminData'])
        ->get();
    }

    /**
     * Check if email has pending invitation
     */
    public function hasPendingInvitation(string $email): bool
    {
        return $this->model->where('email', $email)
            ->whereHas('status', function ($query) {
                $query->whereIn('name', ['sent', 'pending', 'corrections_needed']);
            })
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * Delete invitation
     */
    public function delete(int $id): bool
    {
        return $this->model->where('id', $id)->delete() > 0;
    }

    /**
     * Get invitation statistics
     */
    public function getStatistics(): array
    {
        $stats = DB::table('invitations')
            ->select('invitation_statuses.name as status', DB::raw('count(*) as count'))
            ->join('invitation_statuses', 'invitations.status_id', '=', 'invitation_statuses.id')
            ->groupBy('invitation_statuses.name')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'total' => array_sum($stats),
            'sent' => $stats['sent'] ?? 0,
            'pending' => $stats['pending'] ?? 0,
            'approved' => $stats['approved'] ?? 0,
            'rejected' => $stats['rejected'] ?? 0,
            'corrections_needed' => $stats['corrections_needed'] ?? 0,
        ];
    }
}
