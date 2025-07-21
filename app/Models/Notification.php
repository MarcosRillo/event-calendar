<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'invitation_id', 
        'type', 
        'recipient_email', 
        'content', 
        'sent_at'
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function invitation()
    {
        return $this->belongsTo(Invitation::class);
    }
}
