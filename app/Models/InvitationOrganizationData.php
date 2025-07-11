<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvitationOrganizationData extends Model
{
    protected $fillable = [
        'invitation_id',
        'name',
        'slug',
        'website_url',
        'address',
        'phone',
        'email',
    ];

    public function invitation()
    {
        return $this->belongsTo(Invitation::class);
    }
}
