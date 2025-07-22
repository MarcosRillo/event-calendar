<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Models\Invitation;
use App\Models\InvitationOrganizationData;
use App\Models\InvitationAdminData;
use App\Models\InvitationStatus;
use App\Models\User;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Notification;
use App\Services\EmailService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class OrganizationRequestController extends Controller
{
    protected $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * Crear solicitud pública de organización (sin invitación previa)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function createPublicRequest(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'organization_name' => ['required', 'string', 'max:255'],
                'contact_name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:invitations,email'],
                'phone' => ['nullable', 'string', 'max:50'],
                'description' => ['required', 'string', 'max:1000'],
                'website' => ['nullable', 'url', 'max:255'],
                'address' => ['nullable', 'string', 'max:500'],
                'message' => ['nullable', 'string', 'max:500']
            ]);

            // Buscar estado 'pending'
            $pendingStatus = InvitationStatus::where('name', 'pending')->first();
            if (!$pendingStatus) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de configuración del sistema',
                    'error' => 'SYSTEM_ERROR'
                ], 500);
            }

            // Crear la invitación
            $invitation = Invitation::create([
                'email' => $data['email'],
                'token' => Str::random(64),
                'status_id' => $pendingStatus->id,
                'expires_at' => now()->addDays(config('app.invitation_expires_days', 7)),
                'created_by' => 1, // ID del sistema o super admin por defecto
                'updated_by' => 1,
            ]);

            // Crear datos de la organización
            InvitationOrganizationData::create([
                'invitation_id' => $invitation->id,
                'name' => $data['organization_name'],
                'slug' => Str::slug($data['organization_name']),
                'website_url' => $data['website'] ?? null,
                'address' => $data['address'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'],
            ]);

            // Crear datos del admin
            InvitationAdminData::create([
                'invitation_id' => $invitation->id,
                'first_name' => explode(' ', $data['contact_name'])[0],
                'last_name' => explode(' ', $data['contact_name'], 2)[1] ?? '',
                'email' => $data['email'],
            ]);

            Log::info('Solicitud pública de organización creada', [
                'invitation_id' => $invitation->id,
                'organization_name' => $data['organization_name'],
                'email' => $data['email']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Solicitud enviada exitosamente. Te contactaremos pronto.',
                'invitation' => [
                    'id' => $invitation->id,
                    'organization_name' => $data['organization_name'],
                    'contact_name' => $data['contact_name'],
                    'email' => $data['email'],
                    'status' => 'pending'
                ]
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            Log::error('Error al crear solicitud pública de organización', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Enviar invitación por email para crear organización
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function sendInvitation(Request $request): JsonResponse
    {
        try {
            // Verificar que sea super admin
            if (!Auth::check() || Auth::user()->role->name !== 'superadmin') {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para realizar esta acción',
                    'error' => 'FORBIDDEN'
                ], 403);
            }

            $data = $request->validate([
                'email' => ['required', 'email', 'max:255'],
                'expires_days' => ['nullable', 'integer', 'min:1', 'max:365'],
                'message' => ['nullable', 'string', 'max:1000']
            ]);

            DB::beginTransaction();

            // Verificar si ya existe una invitación pendiente para este email
            $existingInvitation = Invitation::where('email', $data['email'])
                ->whereIn('status_id', [1, 2]) // sent, pending
                ->where('expires_at', '>', now())
                ->first();

            if ($existingInvitation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe una invitación pendiente para este email',
                    'error' => 'INVITATION_EXISTS'
                ], 422);
            }

            // Crear la invitación
            $invitation = Invitation::create([
                'email' => $data['email'],
                'token' => Str::random(64),
                'status_id' => 1, // sent
                'expires_at' => now()->addDays($data['expires_days'] ?? 30),
                'created_by' => Auth::id(),
            ]);

            // Crear notificación
            $notification = Notification::create([
                'invitation_id' => $invitation->id,
                'type' => 'invitation_sent',
                'recipient_email' => $data['email'],
                'content' => $data['message'] ?? 'Te han invitado a crear una organización en el sistema de eventos.',
                'sent_at' => now()
            ]);

            // Enviar email de invitación
            $emailSent = $this->emailService->sendOrganizationInvitation($invitation, $data['message'] ?? null);
            
            if (!$emailSent) {
                Log::warning('Email de invitación no pudo ser enviado', [
                    'invitation_id' => $invitation->id,
                    'email' => $data['email']
                ]);
            }

            DB::commit();

            Log::info('Invitación de organización enviada', [
                'invitation_id' => $invitation->id,
                'email' => $data['email'],
                'sent_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Invitación enviada exitosamente',
                'data' => [
                    'invitation_id' => $invitation->id,
                    'email' => $invitation->email,
                    'token' => $invitation->token,
                    'expires_at' => $invitation->expires_at->toISOString(),
                    'public_url' => config('app.frontend_url') . '/organization-request/' . $invitation->token
                ]
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al enviar invitación de organización', [
                'error' => $e->getMessage(),
                'email' => $request->input('email'),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al enviar la invitación',
                'error' => 'INTERNAL_SERVER_ERROR'
            ], 500);
        }
    }

    /**
     * Verificar token de invitación (para formulario público)
     * 
     * @param string $token
     * @return JsonResponse
     */
    public function verifyToken(string $token): JsonResponse
    {
        try {
            $invitation = Invitation::where('token', $token)
                ->whereIn('status_id', [1, 2]) // sent, pending
                ->where('expires_at', '>', now())
                ->first();

            if (!$invitation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token inválido o expirado',
                    'error' => 'INVALID_TOKEN'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Token válido',
                'data' => [
                    'email' => $invitation->email,
                    'expires_at' => $invitation->expires_at->toISOString(),
                    'is_expired' => $invitation->expires_at->isPast()
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Error al verificar token', [
                'token' => $token,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al verificar el token',
                'error' => 'INTERNAL_SERVER_ERROR'
            ], 500);
        }
    }

    /**
     * Completar solicitud desde formulario público
     * 
     * @param Request $request
     * @param string $token
     * @return JsonResponse
     */
    public function submitRequest(Request $request, string $token): JsonResponse
    {
        try {
            // Verificar token
            $invitation = Invitation::where('token', $token)
                ->whereIn('status_id', [1, 2]) // sent, pending
                ->where('expires_at', '>', now())
                ->first();

            if (!$invitation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token inválido o expirado',
                    'error' => 'INVALID_TOKEN'
                ], 404);
            }

            // Validar datos de la solicitud
            $data = $request->validate([
                'organization.name' => ['required', 'string', 'max:255'],
                'organization.slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9\-]+$/'],
                'organization.website_url' => ['nullable', 'url', 'max:255'],
                'organization.address' => ['nullable', 'string', 'max:500'],
                'organization.phone' => ['nullable', 'string', 'max:20'],
                'organization.email' => ['nullable', 'email', 'max:255'],
                'admin.first_name' => ['required', 'string', 'max:100'],
                'admin.last_name' => ['required', 'string', 'max:100'],
                'admin.email' => ['required', 'email', 'max:255']
            ]);

            DB::beginTransaction();

            // Verificar que el slug no esté en uso
            $existingOrg = Organization::where('slug', $data['organization']['slug'])->first();
            $existingOrgData = InvitationOrganizationData::where('slug', $data['organization']['slug'])->first();
            
            if ($existingOrg || $existingOrgData) {
                return response()->json([
                    'success' => false,
                    'message' => 'El slug ya está en uso',
                    'error' => 'SLUG_EXISTS'
                ], 422);
            }

            // Verificar que el email del admin no esté en uso
            $existingAdmin = User::where('email', $data['admin']['email'])->first();
            $existingAdminData = InvitationAdminData::where('email', $data['admin']['email'])->first();
            
            if ($existingAdmin || $existingAdminData) {
                return response()->json([
                    'success' => false,
                    'message' => 'El email del administrador ya está registrado',
                    'error' => 'ADMIN_EMAIL_EXISTS'
                ], 422);
            }

            // Crear datos de la organización
            $organizationData = InvitationOrganizationData::create([
                'invitation_id' => $invitation->id,
                'name' => $data['organization']['name'],
                'slug' => $data['organization']['slug'],
                'website_url' => $data['organization']['website_url'] ?? null,
                'address' => $data['organization']['address'] ?? null,
                'phone' => $data['organization']['phone'] ?? null,
                'email' => $data['organization']['email'] ?? null,
            ]);

            // Crear datos del administrador
            $adminData = InvitationAdminData::create([
                'invitation_id' => $invitation->id,
                'first_name' => $data['admin']['first_name'],
                'last_name' => $data['admin']['last_name'],
                'email' => $data['admin']['email'],
            ]);

            // Actualizar estado de la invitación
            $invitation->update([
                'status_id' => 2, // pending
                'updated_by' => null // No hay usuario autenticado en formulario público
            ]);

            // Crear notificación
            Notification::create([
                'invitation_id' => $invitation->id,
                'type' => 'request_submitted',
                'recipient_email' => $invitation->email,
                'content' => 'Solicitud de organización completada y pendiente de revisión.',
                'sent_at' => now()
            ]);

            DB::commit();

            Log::info('Solicitud de organización completada', [
                'invitation_id' => $invitation->id,
                'organization_name' => $data['organization']['name'],
                'admin_email' => $data['admin']['email']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Solicitud enviada exitosamente. Recibirás una respuesta por email.',
                'data' => [
                    'invitation_id' => $invitation->id,
                    'status' => 'pending'
                ]
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al completar solicitud de organización', [
                'token' => $token,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud',
                'error' => 'INTERNAL_SERVER_ERROR'
            ], 500);
        }
    }

    /**
     * Listar todas las solicitudes (para super admin)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function listRequests(Request $request): JsonResponse
    {
        try {
            // Verificar permisos
            if (!Auth::check() || Auth::user()->role->name !== 'superadmin') {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para realizar esta acción',
                    'error' => 'FORBIDDEN'
                ], 403);
            }

            $query = Invitation::with([
                'status',
                'organizationData',
                'adminData',
                'createdBy:id,first_name,last_name,email',
                'updatedBy:id,first_name,last_name,email'
            ]);

            // Filtros
            if ($request->has('status')) {
                $statusParam = $request->input('status');
                $statuses = is_array($statusParam) ? $statusParam : [$statusParam];
                $statusIds = InvitationStatus::whereIn('name', $statuses)->pluck('id');
                $query->whereIn('status_id', $statusIds);
            }

            if ($request->has('search')) {
                $search = $request->input('search');
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

            // Ordenamiento
            $sortBy = $request->input('sort_by', 'created_at');
            $sortDirection = $request->input('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            // Paginación
            $perPage = $request->input('per_page', 15);
            $requests = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Solicitudes obtenidas exitosamente',
                'data' => $requests
            ]);

        } catch (Exception $e) {
            Log::error('Error al obtener solicitudes', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las solicitudes',
                'error' => 'INTERNAL_SERVER_ERROR'
            ], 500);
        }
    }

    /**
     * Obtener detalles de una solicitud específica
     * 
     * @param int $invitationId
     * @return JsonResponse
     */
    public function getRequest(int $invitationId): JsonResponse
    {
        try {
            // Verificar permisos
            if (!Auth::check() || Auth::user()->role->name !== 'superadmin') {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para realizar esta acción',
                    'error' => 'FORBIDDEN'
                ], 403);
            }

            $invitation = Invitation::with([
                'status',
                'organizationData',
                'adminData',
                'createdBy:id,first_name,last_name,email',
                'updatedBy:id,first_name,last_name,email',
                'notifications' => function ($query) {
                    $query->orderBy('sent_at', 'desc');
                }
            ])->findOrFail($invitationId);

            return response()->json([
                'success' => true,
                'message' => 'Solicitud obtenida exitosamente',
                'data' => $invitation
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Solicitud no encontrada',
                'error' => 'NOT_FOUND'
            ], 404);

        } catch (Exception $e) {
            Log::error('Error al obtener solicitud', [
                'invitation_id' => $invitationId,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la solicitud',
                'error' => 'INTERNAL_SERVER_ERROR'
            ], 500);
        }
    }

    /**
     * Aprobar, rechazar o solicitar correcciones
     * 
     * @param Request $request
     * @param int $invitationId
     * @return JsonResponse
     */
    public function updateRequestStatus(Request $request, int $invitationId): JsonResponse
    {
        try {
            // Verificar permisos
            if (!Auth::check() || Auth::user()->role->name !== 'superadmin') {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para realizar esta acción',
                    'error' => 'FORBIDDEN'
                ], 403);
            }

            $data = $request->validate([
                'action' => ['required', 'in:approve,reject,request_corrections'],
                'message' => ['nullable', 'string', 'max:1000'],
                'corrections_notes' => ['required_if:action,request_corrections', 'string', 'max:1000']
            ]);

            $invitation = Invitation::with(['organizationData', 'adminData'])->findOrFail($invitationId);

            // Verificar que esté en estado pendiente o que necesite correcciones
            $allowedStatuses = InvitationStatus::whereIn('name', ['pending', 'corrections_needed'])->pluck('id')->toArray();
            if (!in_array($invitation->status_id, $allowedStatuses)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden procesar solicitudes pendientes o que necesiten correcciones',
                    'error' => 'INVALID_STATUS'
                ], 422);
            }

            DB::beginTransaction();

            $actionResult = match($data['action']) {
                'approve' => $this->approveRequest($invitation, $data['message'] ?? null),
                'reject' => $this->rejectRequest($invitation, $data['message'] ?? null),
                'request_corrections' => $this->requestCorrections($invitation, $data['corrections_notes'], $data['message'] ?? null),
            };

            if (!$actionResult['success']) {
                DB::rollBack();
                return response()->json($actionResult, 422);
            }

            // Actualizar campos comunes
            $invitation->update([
                'updated_by' => Auth::id()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $actionResult['message'],
                'data' => [
                    'invitation_id' => $invitationId,
                    'action' => $data['action'],
                    'processed_at' => now()->toISOString()
                ]
            ]);

        } catch (ValidationException $e) {
            // Re-lanzar la excepción de validación para que Laravel la maneje
            throw $e;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar estado de solicitud', [
                'invitation_id' => $invitationId,
                'action' => $request->input('action'),
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud',
                'error' => 'INTERNAL_SERVER_ERROR'
            ], 500);
        }
    }

    /**
     * Aprobar solicitud y crear organización + usuario
     */
    private function approveRequest(Invitation $invitation, ?string $message): array
    {
        try {
            // Generar contraseña temporal
            $tempPassword = Str::random(12);
            
            // Crear la organización
            $organization = Organization::create([
                'name' => $invitation->organizationData->name,
                'slug' => $invitation->organizationData->slug,
                'website_url' => $invitation->organizationData->website_url,
                'address' => $invitation->organizationData->address,
                'phone' => $invitation->organizationData->phone,
                'email' => $invitation->organizationData->email,
                'parent_id' => null, // Set to null for testing
                'trust_level_id' => null, // Set to null for testing
                'is_active' => true,
                'created_by' => Auth::id(),
            ]);

            // Crear el usuario administrador
            $adminRole = Role::where('name', 'admin')->first();
            $user = User::create([
                'organization_id' => $organization->id,
                'role_id' => $adminRole->id,
                'first_name' => $invitation->adminData->first_name,
                'last_name' => $invitation->adminData->last_name,
                'email' => $invitation->adminData->email,
                'password' => bcrypt($tempPassword),
                'is_active' => true,
                'created_by' => Auth::id(),
            ]);

            // Asignar admin a la organización
            $organization->update(['admin_id' => $user->id]);

            // Actualizar estado de invitación
            $approvedStatusId = InvitationStatus::where('name', 'approved')->first()->id;
            $invitation->update([
                'status_id' => $approvedStatusId,
                'accepted_at' => now(),
                'organization_id' => $organization->id
            ]);

            // Crear notificación
            Notification::create([
                'invitation_id' => $invitation->id,
                'type' => 'request_approved',
                'recipient_email' => $invitation->adminData->email,
                'content' => $message ?? 'Tu solicitud de organización ha sido aprobada. Recibirás un email con las credenciales de acceso.',
                'sent_at' => now()
            ]);

            // Enviar email de aprobación con credenciales
            $emailSent = $this->emailService->sendApprovalNotification($invitation, $tempPassword, $message);
            
            if (!$emailSent) {
                Log::warning('Email de aprobación no pudo ser enviado', [
                    'invitation_id' => $invitation->id,
                    'email' => $invitation->adminData->email
                ]);
            }

            return [
                'success' => true,
                'message' => 'Solicitud aprobada exitosamente'
            ];

        } catch (Exception $e) {
            Log::error('Error al aprobar solicitud', [
                'invitation_id' => $invitation->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Error al aprobar la solicitud'
            ];
        }
    }

    /**
     * Rechazar solicitud
     */
    private function rejectRequest(Invitation $invitation, ?string $message): array
    {
        try {
            // Actualizar estado de invitación
            $rejectedStatusId = InvitationStatus::where('name', 'rejected')->first()->id;
            $invitation->update([
                'status_id' => $rejectedStatusId,
                'rejected_reason' => $message
            ]);

            // Crear notificación
            Notification::create([
                'invitation_id' => $invitation->id,
                'type' => 'request_rejected',
                'recipient_email' => $invitation->adminData->email,
                'content' => $message ?? 'Tu solicitud de organización ha sido rechazada.',
                'sent_at' => now()
            ]);

            // Enviar email de rechazo
            $emailSent = $this->emailService->sendRejectionNotification($invitation, $message);
            
            if (!$emailSent) {
                Log::warning('Email de rechazo no pudo ser enviado', [
                    'invitation_id' => $invitation->id,
                    'email' => $invitation->adminData->email
                ]);
            }

            return [
                'success' => true,
                'message' => 'Solicitud rechazada exitosamente'
            ];

        } catch (Exception $e) {
            Log::error('Error al rechazar solicitud', [
                'invitation_id' => $invitation->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Error al rechazar la solicitud'
            ];
        }
    }

    /**
     * Solicitar correcciones
     */
    private function requestCorrections(Invitation $invitation, string $corrections, ?string $message): array
    {
        try {
            $invitation->update([
                'status_id' => 5, // corrections_needed
                'corrections_notes' => $corrections
            ]);

            Notification::create([
                'invitation_id' => $invitation->id,
                'type' => 'corrections_requested',
                'recipient_email' => $invitation->adminData->email,
                'content' => $message ?? 'Se requieren correcciones en tu solicitud de organización.',
                'sent_at' => now()
            ]);

            // Enviar email de solicitud de correcciones
            $emailSent = $this->emailService->sendCorrectionsRequest($invitation, $corrections, $message);
            
            if (!$emailSent) {
                Log::warning('Email de solicitud de correcciones no pudo ser enviado', [
                    'invitation_id' => $invitation->id,
                    'email' => $invitation->adminData->email
                ]);
            }

            return [
                'success' => true,
                'message' => 'Correcciones solicitadas exitosamente'
            ];

        } catch (Exception $e) {
            Log::error('Error al solicitar correcciones', [
                'invitation_id' => $invitation->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Error al solicitar correcciones'
            ];
        }
    }

    /**
     * Obtener estadísticas de solicitudes de organización
     * 
     * @return JsonResponse
     */
    public function getStatistics(): JsonResponse
    {
        try {
            // Verificar permisos
            if (!Auth::check() || Auth::user()->role->name !== 'superadmin') {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para realizar esta acción',
                    'error' => 'FORBIDDEN'
                ], 403);
            }

            $stats = [];
            
            // Obtener todas las invitaciones
            $total = Invitation::count();
            $stats['total'] = $total;

            // Obtener estadísticas por estado
            $statusCounts = Invitation::join('invitation_statuses', 'invitations.status_id', '=', 'invitation_statuses.id')
                ->select('invitation_statuses.name', DB::raw('count(*) as count'))
                ->groupBy('invitation_statuses.name')
                ->pluck('count', 'name')
                ->toArray();

            $stats['pending'] = $statusCounts['pending'] ?? 0;
            $stats['approved'] = $statusCounts['approved'] ?? 0;
            $stats['rejected'] = $statusCounts['rejected'] ?? 0;

            return response()->json($stats);

        } catch (Exception $e) {
            Log::error('Error al obtener estadísticas de solicitudes', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las estadísticas',
                'error' => 'INTERNAL_SERVER_ERROR'
            ], 500);
        }
    }
}
