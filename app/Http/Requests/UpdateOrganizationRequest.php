<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use App\Models\Organization;

class UpdateOrganizationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = Auth::user();
        return $user && $user instanceof \App\Models\User && $user->isSuperAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $organizationId = $this->route('organization');
        
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes', 
                'required', 
                'string', 
                'max:255',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('organizations', 'slug')->ignore($organizationId)->whereNull('deleted_at')
            ],
            'website_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'email' => [
                'sometimes', 
                'nullable', 
                'email', 
                'max:255',
                Rule::unique('organizations', 'email')->ignore($organizationId)->whereNull('deleted_at')
            ],
            'admin_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'trust_level_id' => ['sometimes', 'nullable', 'exists:trust_levels,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la organización es obligatorio.',
            'name.max' => 'El nombre no puede exceder 255 caracteres.',
            'slug.required' => 'El slug es obligatorio.',
            'slug.unique' => 'Este slug ya está en uso por otra organización.',
            'slug.regex' => 'El slug solo puede contener letras minúsculas, números y guiones.',
            'slug.max' => 'El slug no puede exceder 255 caracteres.',
            'website_url.url' => 'La URL del sitio web debe ser válida.',
            'website_url.max' => 'La URL no puede exceder 255 caracteres.',
            'address.max' => 'La dirección no puede exceder 500 caracteres.',
            'phone.max' => 'El teléfono no puede exceder 20 caracteres.',
            'email.email' => 'El email debe ser una dirección válida.',
            'email.unique' => 'Este email ya está en uso por otra organización.',
            'email.max' => 'El email no puede exceder 255 caracteres.',
            'admin_id.exists' => 'El administrador seleccionado no existe.',
            'trust_level_id.exists' => 'El nivel de confianza seleccionado no existe.',
            'is_active.boolean' => 'El estado activo debe ser verdadero o falso.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'slug' => 'slug',
            'website_url' => 'sitio web',
            'address' => 'dirección',
            'phone' => 'teléfono',
            'email' => 'email',
            'admin_id' => 'administrador',
            'trust_level_id' => 'nivel de confianza',
            'is_active' => 'estado activo',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Auto-generate slug if name is provided but slug is not
        if ($this->has('name') && !$this->has('slug')) {
            $this->merge([
                'slug' => \Illuminate\Support\Str::slug($this->name)
            ]);
        }

        // Trim string fields
        $stringFields = ['name', 'slug', 'website_url', 'address', 'phone', 'email'];
        foreach ($stringFields as $field) {
            if ($this->has($field) && is_string($this->get($field))) {
                $this->merge([
                    $field => trim($this->get($field)) ?: null
                ]);
            }
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Additional validation logic can be added here
            
            // Check if trying to deactivate organization with active events
            if ($this->has('is_active') && $this->is_active === false) {
                $organizationId = $this->route('organization');
                $organization = Organization::find($organizationId);
                
                if ($organization) {
                    $activeEventsCount = $organization->events()
                        ->whereNotIn('status_id', [4, 5]) // Assuming 4=cancelled, 5=rejected
                        ->count();
                    
                    if ($activeEventsCount > 0) {
                        $validator->errors()->add(
                            'is_active', 
                            "No se puede desactivar la organización porque tiene {$activeEventsCount} evento(s) activo(s)."
                        );
                    }
                }
            }
        });
    }
}
