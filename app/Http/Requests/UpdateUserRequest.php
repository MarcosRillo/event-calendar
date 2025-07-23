<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->isSuperAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->route('userId');

        return [
            'first_name' => [
                'required',
                'string',
                'min:2',
                'max:255'
            ],
            'last_name' => [
                'required',
                'string',
                'min:2',
                'max:255'
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId)
            ],
            'phone' => [
                'nullable',
                'string',
                'max:20'
            ],
            'role_id' => [
                'required',
                'exists:roles,id'
            ],
            'organization_id' => [
                'nullable',
                'exists:organizations,id'
            ],
            'is_active' => [
                'boolean'
            ],
            'password' => [
                'nullable',
                'string',
                'min:8',
                'max:255',
                'confirmed'
            ],
            'password_confirmation' => [
                'nullable',
                'required_with:password',
                'string'
            ]
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'El nombre es obligatorio.',
            'first_name.string' => 'El nombre debe ser una cadena de texto.',
            'first_name.min' => 'El nombre debe tener al menos 2 caracteres.',
            'first_name.max' => 'El nombre no puede exceder 255 caracteres.',
            
            'last_name.required' => 'El apellido es obligatorio.',
            'last_name.string' => 'El apellido debe ser una cadena de texto.',
            'last_name.min' => 'El apellido debe tener al menos 2 caracteres.',
            'last_name.max' => 'El apellido no puede exceder 255 caracteres.',
            
            'email.required' => 'El email es obligatorio.',
            'email.email' => 'Debe ser un email válido.',
            'email.unique' => 'Este email ya está en uso.',
            'email.max' => 'El email no puede exceder 255 caracteres.',
            
            'phone.string' => 'El teléfono debe ser una cadena de texto.',
            'phone.max' => 'El teléfono no puede exceder 20 caracteres.',
            
            'role_id.required' => 'El rol es obligatorio.',
            'role_id.exists' => 'El rol seleccionado no existe.',
            
            'organization_id.exists' => 'La organización seleccionada no existe.',
            
            'is_active.boolean' => 'El estado activo debe ser verdadero o falso.',
            
            'password.string' => 'La contraseña debe ser una cadena de texto.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.max' => 'La contraseña no puede exceder 255 caracteres.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
            
            'password_confirmation.required_with' => 'Debe confirmar la contraseña.'
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Trim and clean input data if they exist
        $mergeData = [];
        
        if ($this->has('first_name')) {
            $mergeData['first_name'] = trim(ucwords(strtolower($this->input('first_name'))));
        }
        
        if ($this->has('last_name')) {
            $mergeData['last_name'] = trim(ucwords(strtolower($this->input('last_name'))));
        }
        
        if ($this->has('email')) {
            $mergeData['email'] = trim(strtolower($this->input('email')));
        }
        
        if ($this->has('phone')) {
            $mergeData['phone'] = trim($this->input('phone'));
        }
        
        // Convert is_active to boolean if provided
        if ($this->has('is_active')) {
            $mergeData['is_active'] = filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN);
        }
        
        $this->merge($mergeData);
    }

    /**
     * Get validated data with additional processing.
     */
    public function getValidatedData(): array
    {
        $data = $this->validated();
        
        // Remove empty password if not provided
        if (empty($data['password'])) {
            unset($data['password'], $data['password_confirmation']);
        } else {
            // Hash password if provided
            $data['password'] = bcrypt($data['password']);
        }
        
        // Remove confirmation field as it's not needed for storage
        unset($data['password_confirmation']);
        
        return $data;
    }
}
