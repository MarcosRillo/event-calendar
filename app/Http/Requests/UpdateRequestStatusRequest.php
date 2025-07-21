<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Validation\Rule;

class UpdateRequestStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Solo super admins pueden actualizar el estado de solicitudes
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
            'action' => [
                'required',
                'string',
                Rule::in(['approve', 'reject', 'request_corrections']),
            ],
            'message' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'corrections_notes' => [
                'required_if:action,request_corrections',
                'nullable',
                'string',
                'max:2000',
                'min:10',
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
            'action.required' => 'La acción es obligatoria.',
            'action.in' => 'La acción debe ser: aprobar, rechazar o solicitar correcciones.',
            'message.max' => 'El mensaje no puede exceder 1000 caracteres.',
            'corrections_notes.required_if' => 'Las notas de corrección son obligatorias cuando se solicitan correcciones.',
            'corrections_notes.min' => 'Las notas de corrección deben tener al menos 10 caracteres.',
            'corrections_notes.max' => 'Las notas de corrección no pueden exceder 2000 caracteres.',
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
            'action' => 'acción',
            'message' => 'mensaje',
            'corrections_notes' => 'notas de corrección',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Limpiar el mensaje si está vacío
        if ($this->message === '') {
            $this->merge(['message' => null]);
        }

        // Limpiar corrections_notes si está vacío
        if ($this->corrections_notes === '') {
            $this->merge(['corrections_notes' => null]);
        }
    }
}
