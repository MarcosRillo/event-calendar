<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationStyle extends Model
{
    protected $fillable = ['organization_id', 'name', 'value'];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
