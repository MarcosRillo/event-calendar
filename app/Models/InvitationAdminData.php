<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InvitationAdminData extends Model
{
    use HasFactory;
    protected $fillable = [
        'invitation_id',
        'first_name',
        'last_name',
        'email',
    ];

    public function invitation()
    {
        return $this->belongsTo(Invitation::class);
    }
}
