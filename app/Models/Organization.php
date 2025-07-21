<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'website_url',
        'address',
        'phone',
        'email',
        'admin_id',
        'parent_id',
        'trust_level_id',
        'is_active',
        'created_by',
    ];

    public function trustLevel()
    {
        return $this->belongsTo(TrustLevel::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function parent()
    {
        return $this->belongsTo(Organization::class, 'parent_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }

    public function invitations()
    {
        return $this->hasMany(Invitation::class);
    }

    public function styles()
    {
        return $this->hasMany(OrganizationStyle::class);
    }

    public function formFields()
    {
        return $this->hasMany(FormField::class);
    }
}
