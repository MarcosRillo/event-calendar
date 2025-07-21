<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InvitationOrganizationData extends Model
{
    use HasFactory;
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
