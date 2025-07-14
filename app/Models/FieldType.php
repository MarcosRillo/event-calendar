<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FieldType extends Model
{
    protected $fillable = ['name'];

    public function formFields()
    {
        return $this->hasMany(FormField::class);
    }
}
?>