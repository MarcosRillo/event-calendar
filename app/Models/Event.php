<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'category_id',
        'status_id',
        'title',
        'description',
        'start_date',
        'end_date',
        'location',
        'is_public',
        'banner',
        'flyer',
        'created_by',
        'rejected_reason',
        'corrections_notes',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_public' => 'boolean',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function category()
    {
        return $this->belongsTo(EventCategory::class);
    }

    public function status()
    {
        return $this->belongsTo(EventStatus::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function formFields()
    {
        return $this->hasMany(FormField::class);
    }

    public function formSubmissions()
    {
        return $this->hasMany(EventFormSubmission::class);
    }
}
