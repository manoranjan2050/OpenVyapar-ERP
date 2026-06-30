<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Branch extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'company_id', 'name', 'gstin', 'phone',
        'address', 'city', 'state', 'pincode', 'is_head_office', 'is_active',
    ];

    protected $casts = ['is_head_office' => 'boolean', 'is_active' => 'boolean'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) $model->uuid = (string) Str::uuid();
        });
    }

    public function company() { return $this->belongsTo(Company::class); }
    public function users() { return $this->hasMany(User::class); }
    public function warehouses() { return $this->hasMany(Warehouse::class); }
}
