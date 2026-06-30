<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'name', 'legal_name', 'gstin', 'pan', 'phone', 'email',
        'address', 'city', 'state', 'pincode', 'country', 'currency',
        'logo_path', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) $model->uuid = (string) Str::uuid();
        });
    }

    public function branches() { return $this->hasMany(Branch::class); }
    public function users() { return $this->hasMany(User::class); }
    public function financialYears() { return $this->hasMany(FinancialYear::class); }
    public function categories() { return $this->hasMany(Category::class); }
    public function units() { return $this->hasMany(Unit::class); }
    public function products() { return $this->hasMany(Product::class); }
    public function customers() { return $this->hasMany(Customer::class); }
    public function suppliers() { return $this->hasMany(Supplier::class); }
    public function warehouses() { return $this->hasMany(Warehouse::class); }
    public function salesInvoices() { return $this->hasMany(SalesInvoice::class); }
    public function purchaseInvoices() { return $this->hasMany(PurchaseInvoice::class); }
    public function settings() { return $this->hasMany(Setting::class); }
}
