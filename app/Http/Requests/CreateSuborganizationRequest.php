<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CreateSuborganizationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Solo usuarios autenticados pueden crear solicitudes
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'organization' => ['required', 'array'],
            'organization.name' => ['required', 'string', 'max:255'],
            'organization.slug' => [
                'required', 
                'string', 
                'max:100', 
                'regex:/^[a-z0-9-]+$/',
                'unique:invitation_organization_data,slug'
            ],
            'organization.website_url' => ['nullable', 'url', 'max:255'],
            'organization.address' => ['nullable', 'string', 'max:500'],
            'organization.phone' => ['nullable', 'string', 'max:20'],
            'organization.email' => ['nullable', 'email', 'max:255'],
            'admin' => ['required', 'array'],
            'admin.first_name' => ['required', 'string', 'max:100'],
            'admin.last_name' => ['required', 'string', 'max:100'],
            'admin.email' => ['required', 'email', 'max:255'],
            'admin.phone' => ['nullable', 'string', 'max:20'],
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
            'email.required' => 'El email es obligatorio',
            'email.email' => 'El email debe tener un formato válido',
            'organization.required' => 'Los datos de la organización son obligatorios',
            'organization.name.required' => 'El nombre de la organización es obligatorio',
            'organization.name.max' => 'El nombre de la organización no puede exceder 255 caracteres',
            'organization.slug.required' => 'El slug de la organización es obligatorio',
            'organization.slug.unique' => 'Este slug ya está en uso',
            'organization.slug.regex' => 'El slug solo puede contener letras minúsculas, números y guiones',
            'organization.website_url.url' => 'La URL del sitio web debe ser válida',
            'organization.email.email' => 'El email de la organización debe ser válido',
            'admin.required' => 'Los datos del administrador son obligatorios',
            'admin.first_name.required' => 'El nombre del administrador es obligatorio',
            'admin.last_name.required' => 'El apellido del administrador es obligatorio',
            'admin.email.required' => 'El email del administrador es obligatorio',
            'admin.email.email' => 'El email del administrador debe ser válido',
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
            'organization.slug' => 'slug de la organización',
            'organization.website_url' => 'sitio web',
            'organization.address' => 'dirección',
            'organization.phone' => 'teléfono de la organización',
            'organization.email' => 'email de la organización',
            'admin.first_name' => 'nombre del administrador',
            'admin.last_name' => 'apellido del administrador',
            'admin.email' => 'email del administrador',
            'admin.phone' => 'teléfono del administrador',
        ];
    }
}
