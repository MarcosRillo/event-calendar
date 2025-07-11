<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvitationAdminData extends Model
{
    protected $fillable = [
        'invitation_id',
        'first_name',
        'last_name',
        'email',
        'phone',
    ];

    public function invitation()
    {
        return $this->belongsTo(Invitation::class);
    }
}
