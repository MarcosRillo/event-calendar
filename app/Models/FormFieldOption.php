<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormFieldOption extends Model
{
    protected $fillable = ['form_field_id', 'value'];

    public function formField()
    {
        return $this->belongsTo(FormField::class);
    }
}
