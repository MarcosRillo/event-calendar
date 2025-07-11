<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use SoftDeletes, HasApiTokens;

    protected $fillable = [
        'organization_id',
        'role_id',
        'first_name',
        'last_name',
        'email',
        'email_verified_at',
        'password',
        'phone',
        'avatar_url',
        'is_active',
        'created_by',
    ];

    protected $hidden = ['password'];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function createdOrganizations()
    {
        return $this->hasMany(Organization::class, 'created_by');
    }

    public function administeredOrganizations()
    {
        return $this->hasMany(Organization::class, 'admin_id');
    }

    public function events()
    {
        return $this->hasMany(Event::class, 'created_by');
    }

    public function invitations()
    {
        return $this->hasMany(Invitation::class, 'created_by');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function formSubmissions()
    {
        return $this->hasMany(EventFormSubmission::class, 'submitted_by');
    }
}
