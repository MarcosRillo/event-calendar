<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationAdminRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organization = $this->route('organization');
        $currentAdminId = null;

        // Get the current admin ID if the organization exists
        if ($organization) {
            $org = \App\Models\Organization::find($organization);
            $currentAdminId = $org?->admin_id;
        }

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
                Rule::unique('users', 'email')->ignore($currentAdminId)
            ],
            'phone' => [
                'nullable',
                'string',
                'max:20'
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
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'El nombre es obligatorio.',
            'first_name.regex' => 'El nombre solo puede contener letras y espacios.',
            'last_name.required' => 'El apellido es obligatorio.',
            'last_name.regex' => 'El apellido solo puede contener letras y espacios.',
            'email.required' => 'El email es obligatorio.',
            'email.email' => 'El email debe tener un formato válido.',
            'email.unique' => 'Este email ya está en uso por otro usuario.',
            'phone.regex' => 'El teléfono debe tener un formato válido.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
            'password.regex' => 'La contraseña debe contener al menos: una minúscula, una mayúscula, un número y un símbolo especial.',
            'password_confirmation.required_with' => 'Debe confirmar la contraseña.',
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
        }
        
        // Remove confirmation field as it's not needed for storage
        unset($data['password_confirmation']);
        
        return $data;
    }
}
