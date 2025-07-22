<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Invitation;
use App\Models\InvitationStatus;
use App\Services\EmailService;
use App\Services\CacheService;
use Illuminate\Support\Facades\Auth;
use Exception;

class OrganizationRequestAdminController extends Controller
{
    private EmailService $emailService;
    private CacheService $cacheService;

    public function __construct(EmailService $emailService, CacheService $cacheService)
    {
        $this->emailService = $emailService;
        $this->cacheService = $cacheService;
    }

    /**
     * Enviar invitación manual
     */
    public function sendInvitation(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'email' => ['required', 'email', 'max:255'],
                'organization_name' => ['required', 'string', 'max:255'],
                'contact_name' => ['required', 'string', 'max:255'],
                'expires_days' => ['nullable', 'integer', 'min:1', 'max:365'],
                'message' => ['nullable', 'string', 'max:1000']
            ]);

            DB::beginTransaction();

            // Crear la invitación
            $invitation = Invitation::create([
                'email' => $data['email'],
                'token' => \Illuminate\Support\Str::random(64),
                'status_id' => 1, // sent
                'expires_at' => now()->addDays($data['expires_days'] ?? 7),
                'created_by' => Auth::id() ?? 1
            ]);

            // Crear datos básicos de organización
            $invitation->organizationData()->create([
                'name' => $data['organization_name'],
                'slug' => \Illuminate\Support\Str::slug($data['organization_name']),
                'email' => $data['email']
            ]);

            // Crear datos básicos del admin
            $nameParts = explode(' ', $data['contact_name'], 2);
            $invitation->adminData()->create([
                'first_name' => $nameParts[0],
                'last_name' => $nameParts[1] ?? '',
                'email' => $data['email']
            ]);

            DB::commit();

            // Enviar email de invitación
            $emailSent = $this->emailService->sendOrganizationInvitation($invitation, $data['message'] ?? null);
            
            if (!$emailSent) {
                Log::warning('Email de invitación no pudo ser enviado', [
                    'invitation_id' => $invitation->id,
                    'email' => $data['email']
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Invitación enviada exitosamente',
                'data' => [
                    'invitation_id' => $invitation->id
                ]
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('Error al enviar invitación', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al enviar la invitación'
            ], 500);
        }
    }

    /**
     * Listar solicitudes con filtros
     */
    public function listRequests(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'status' => ['nullable', 'string', 'exists:invitation_statuses,name'],
                'search' => ['nullable', 'string', 'max:255'],
                'page' => ['nullable', 'integer', 'min:1'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
                'sort_by' => ['nullable', 'string', 'in:created_at,organization_name,status'],
                'sort_order' => ['nullable', 'string', 'in:asc,desc']
            ]);

            // Always fetch fresh data for organization requests (no cache)
            // Organization requests are highly mutable and need real-time updates
            $result = $this->fetchRequests($data);

            return response()->json([
                'success' => true,
                'message' => 'Solicitudes listadas exitosamente',
                'data' => $result['data'],
                'meta' => $result['meta']
            ]);

        } catch (Exception $e) {
            Log::error('Error al listar solicitudes', [
                'error' => $e->getMessage(),
                'filters' => $data ?? [],
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al listar solicitudes'
            ], 500);
        }
    }

    /**
     * Fetch requests with optimized queries
     */
    private function fetchRequests(array $data): array
    {
        $query = Invitation::with([
            'organizationData', 
            'adminData', 
            'status',
            'createdBy:id,first_name,last_name,email',
            'updatedBy:id,first_name,last_name,email'
        ])->whereNotNull('id');

        // Filtrar por estado
        if (!empty($data['status'])) {
            $query->whereHas('status', function ($q) use ($data) {
                $q->where('name', $data['status']);
            });
        }

        // Búsqueda por texto
        if (!empty($data['search'])) {
            $search = $data['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('organizationData', function ($subQ) use ($search) {
                    $subQ->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                })
                ->orWhereHas('adminData', function ($subQ) use ($search) {
                    $subQ->where('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                });
            });
        }

        // Ordenamiento
        $sortBy = $data['sort_by'] ?? 'priority';
        $sortOrder = $data['sort_order'] ?? 'desc';
        
        if ($sortBy === 'organization_name') {
            $query->join('invitation_organization_data', 'invitations.id', '=', 'invitation_organization_data.invitation_id')
                  ->orderBy('invitation_organization_data.name', $sortOrder)
                  ->select('invitations.*');
        } elseif ($sortBy === 'status') {
            $query->join('invitation_statuses', 'invitations.status_id', '=', 'invitation_statuses.id')
                  ->orderBy('invitation_statuses.name', $sortOrder)
                  ->select('invitations.*');
        } elseif ($sortBy === 'priority') {
            // Ordenar por prioridad: pending y corrections_needed primero, luego por fecha
            $query->orderByRaw("CASE 
                WHEN status_id = 2 THEN 1 
                WHEN status_id = 5 THEN 2 
                WHEN status_id = 1 THEN 3 
                ELSE 4 
            END")
            ->orderBy('updated_at', 'desc');
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Paginación
        $perPage = $data['per_page'] ?? 10;
        $invitations = $query->paginate($perPage);

        return [
            'data' => $invitations->items(),
            'meta' => [
                'current_page' => $invitations->currentPage(),
                'per_page' => $invitations->perPage(),
                'total' => $invitations->total(),
                'last_page' => $invitations->lastPage(),
                'from' => $invitations->firstItem(),
                'to' => $invitations->lastItem()
            ]
        ];
    }

    /**
     * Obtener una solicitud específica
     */
    public function getRequest(int $invitationId): JsonResponse
    {
        try {
            $invitation = Invitation::with([
                'organizationData',
                'adminData',
                'status',
                'organization',
                'createdBy:id,first_name,last_name',
                'updatedBy:id,first_name,last_name'
            ])->findOrFail($invitationId);

            return response()->json([
                'success' => true,
                'data' => $invitation
            ]);

        } catch (Exception $e) {
            Log::error('Error al obtener solicitud', [
                'invitation_id' => $invitationId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Solicitud no encontrada'
            ], 404);
        }
    }

    /**
     * Obtener estadísticas de solicitudes
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $stats = [];
            
            // Contar por estado
            $statusCounts = Invitation::select('invitation_statuses.name', DB::raw('count(*) as count'))
                ->join('invitation_statuses', 'invitations.status_id', '=', 'invitation_statuses.id')
                ->groupBy('invitation_statuses.name')
                ->pluck('count', 'name')
                ->toArray();

            // Estadísticas por mes (últimos 6 meses)
            $monthlyStats = Invitation::select(
                    DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                    DB::raw('count(*) as total')
                )
                ->where('created_at', '>=', now()->subMonths(6))
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            // Tiempo promedio de respuesta
            $avgResponseTime = Invitation::whereNotNull('accepted_at')
                ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, accepted_at)) as avg_hours')
                ->first()
                ->avg_hours ?? 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'status_counts' => $statusCounts,
                    'monthly_stats' => $monthlyStats,
                    'avg_response_time_hours' => round($avgResponseTime, 1),
                    'total_requests' => array_sum($statusCounts)
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Error al obtener estadísticas', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas'
            ], 500);
        }
    }
}
