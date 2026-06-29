<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $fillable = ['company_id', 'name', 'short_name'];

    public function company() { return $this->belongsTo(Company::class); }
    public function products() { return $this->hasMany(Product::class); }
}
