<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int $role_id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string|null $phone
 * @property string|null $avatar_url
 * @property bool $is_active
 * @property int|null $created_by
 * @property-read string $name
 * @property-read Organization|null $organization
 * @property-read Role|null $role
 * @property-read User|null $createdBy
 * @method \Laravel\Sanctum\NewAccessToken createToken(string $name, array $abilities = ['*'])
 */
class User extends Authenticatable
{
    use HasFactory, SoftDeletes, HasApiTokens;

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

    // Computed attribute for full name
    public function getNameAttribute()
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

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

    // Role helper methods
    public function isSuperAdmin(): bool
    {
        return $this->role && $this->role->name === 'superadmin';
    }

    public function isOrganizationAdmin(): bool
    {
        return $this->role && $this->role->name === 'organization_admin';
    }

    public function isOrganizationUser(): bool
    {
        return $this->role && $this->role->name === 'organization_user';
    }

    public function hasRole(string $roleName): bool
    {
        return $this->role && $this->role->name === $roleName;
    }
}
