<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InvitationStatus extends Model
{
    use HasFactory;
    protected $fillable = ['name'];

    public function invitations()
    {
        return $this->hasMany(Invitation::class);
    }
}
