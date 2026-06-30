<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Product extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'sku', 'company_id', 'selling_price', 'purchase_price', 'gst_rate', 'is_active', 'low_stock_alert'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $event) => "Product {$event}");
    }

    protected $fillable = [
        'uuid', 'company_id', 'category_id', 'unit_id', 'name', 'sku', 'barcode',
        'hsn_code', 'gst_rate', 'purchase_price', 'selling_price', 'mrp',
        'opening_stock', 'low_stock_alert', 'description', 'image_path',
        'track_inventory', 'is_active',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'mrp' => 'decimal:2',
        'track_inventory' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) $model->uuid = (string) Str::uuid();
        });
    }

    public function company() { return $this->belongsTo(Company::class); }
    public function category() { return $this->belongsTo(Category::class); }
    public function unit() { return $this->belongsTo(Unit::class); }
    public function stockTransactions() { return $this->hasMany(StockTransaction::class); }

    public function currentStock(): int
    {
        return $this->stockTransactions()->latest('transacted_at')->value('balance_after') ?? $this->opening_stock;
    }
}
