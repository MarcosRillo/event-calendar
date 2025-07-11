<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventFormSubmission extends Model
{
    protected $fillable = ['event_id', 'form_field_id', 'submitted_by', 'value'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function formField()
    {
        return $this->belongsTo(FormField::class);
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}