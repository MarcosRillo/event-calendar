<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class SendInvitationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Solo super admins pueden enviar invitaciones
        if (!Auth::check()) {
            return false;
        }

        $user = Auth::user();
        
        /** @var User $user */
        if (!$user instanceof User) {
            return false;
        }

        if (!$user->relationLoaded('role')) {
            $user->load('role');
        }

        return $user->isSuperAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:invitations,email',
            ],
            'expires_days' => [
                'nullable',
                'integer',
                'min:1',
                'max:365',
            ],
            'message' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'El email es obligatorio.',
            'email.email' => 'El email debe tener un formato válido.',
            'email.unique' => 'Ya existe una invitación pendiente para este email.',
            'expires_days.integer' => 'Los días de expiración deben ser un número entero.',
            'expires_days.min' => 'Los días de expiración deben ser al menos 1.',
            'expires_days.max' => 'Los días de expiración no pueden ser más de 365.',
            'message.max' => 'El mensaje no puede exceder 1000 caracteres.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => 'correo electrónico',
            'expires_days' => 'días de expiración',
            'message' => 'mensaje',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Establecer valor por defecto para expires_days si no se proporciona
        if (!$this->has('expires_days') || is_null($this->expires_days)) {
            $this->merge([
                'expires_days' => config('app.invitation_expires_days', 30),
            ]);
        }
    }
}
