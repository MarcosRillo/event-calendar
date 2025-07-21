<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Invitation extends Model
{
    use HasFactory;
    protected $fillable = [
        'email', 
        'token', 
        'status_id', 
        'corrections_notes',
        'expires_at',
        'accepted_at',
        'created_by', 
        'updated_by',
        'organization_id',
        'rejected_reason'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function status()
    {
        return $this->belongsTo(InvitationStatus::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function organizationData()
    {
        return $this->hasOne(InvitationOrganizationData::class);
    }

    public function adminData()
    {
        return $this->hasOne(InvitationAdminData::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }
}
