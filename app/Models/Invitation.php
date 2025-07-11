<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invitation extends Model
{
    use SoftDeletes;

    protected $fillable = ['email', 'token', 'status_id', 'created_by', 'organization_id'];

    public function status()
    {
        return $this->belongsTo(InvitationStatus::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
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
