<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitOrganizationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Es una ruta pública, cualquiera con token válido puede acceder
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'organization' => ['required', 'array'],
            'organization.name' => [
                'required',
                'string',
                'max:255',
                'min:2',
            ],
            'organization.slug' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9-]+$/',
                'unique:invitation_organization_data,slug',
                'unique:organizations,slug',
            ],
            'organization.website_url' => [
                'nullable',
                'url',
                'max:255',
            ],
            'organization.address' => [
                'required',
                'string',
                'max:500',
                'min:10',
            ],
            'organization.phone' => [
                'required',
                'string',
                'max:20',
                'min:8',
                'regex:/^[\+]?[0-9\s\-\(\)]+$/',
            ],
            'organization.email' => [
                'required',
                'email',
                'max:255',
                'unique:invitation_organization_data,email',
                'unique:organizations,email',
            ],
            'admin' => ['required', 'array'],
            'admin.first_name' => [
                'required',
                'string',
                'max:100',
                'min:2',
                'regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+$/',
            ],
            'admin.last_name' => [
                'required',
                'string',
                'max:100',
                'min:2',
                'regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+$/',
            ],
            'admin.email' => [
                'required',
                'email',
                'max:255',
                'unique:invitation_admin_data,email',
                'unique:users,email',
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
            // Organization messages
            'organization.name.required' => 'El nombre de la organización es obligatorio.',
            'organization.name.min' => 'El nombre de la organización debe tener al menos 2 caracteres.',
            'organization.slug.required' => 'El identificador único es obligatorio.',
            'organization.slug.regex' => 'El identificador solo puede contener letras minúsculas, números y guiones.',
            'organization.slug.unique' => 'Este identificador ya está en uso.',
            'organization.website_url.url' => 'El sitio web debe ser una URL válida.',
            'organization.address.required' => 'La dirección es obligatoria.',
            'organization.address.min' => 'La dirección debe tener al menos 10 caracteres.',
            'organization.phone.required' => 'El teléfono es obligatorio.',
            'organization.phone.regex' => 'El formato del teléfono no es válido.',
            'organization.email.required' => 'El email de contacto es obligatorio.',
            'organization.email.email' => 'El email de contacto debe tener un formato válido.',
            'organization.email.unique' => 'Este email ya está registrado.',

            // Admin messages
            'admin.first_name.required' => 'El nombre del administrador es obligatorio.',
            'admin.first_name.min' => 'El nombre debe tener al menos 2 caracteres.',
            'admin.first_name.regex' => 'El nombre solo puede contener letras y espacios.',
            'admin.last_name.required' => 'El apellido del administrador es obligatorio.',
            'admin.last_name.min' => 'El apellido debe tener al menos 2 caracteres.',
            'admin.last_name.regex' => 'El apellido solo puede contener letras y espacios.',
            'admin.email.required' => 'El email del administrador es obligatorio.',
            'admin.email.email' => 'El email del administrador debe tener un formato válido.',
            'admin.email.unique' => 'Este email ya está registrado.',
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
            'organization.name' => 'nombre de la organización',
            'organization.slug' => 'identificador único',
            'organization.website_url' => 'sitio web',
            'organization.address' => 'dirección',
            'organization.phone' => 'teléfono',
            'organization.email' => 'email de contacto',
            'admin.first_name' => 'nombre del administrador',
            'admin.last_name' => 'apellido del administrador',
            'admin.email' => 'email del administrador',
        ];
    }
}
