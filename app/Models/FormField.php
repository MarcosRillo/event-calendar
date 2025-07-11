<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormField extends Model
{
    protected $fillable = [
        'organization_id',
        'event_id',
        'field_type_id',
        'name',
        'is_required_internal',
        'is_required_public',
        'is_optional',
    ];

    protected $casts = [
        'is_required_internal' => 'boolean',
        'is_required_public' => 'boolean',
        'is_optional' => 'boolean',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function fieldType()
    {
        return $this->belongsTo(FieldType::class);
    }

    public function options()
    {
        return $this->hasMany(FormFieldOption::class);
    }

    public function submissions()
    {
        return $this->hasMany(EventFormSubmission::class);
    }
}
