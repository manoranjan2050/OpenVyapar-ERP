<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Customer extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'phone', 'email', 'gstin', 'company_id', 'credit_limit', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $event) => "Customer {$event}");
    }

    protected $fillable = [
        'uuid', 'company_id', 'name', 'phone', 'email', 'gstin', 'pan',
        'billing_address', 'billing_city', 'billing_state', 'billing_pincode',
        'shipping_address', 'credit_limit', 'credit_days',
        'opening_balance', 'opening_balance_type', 'is_active',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'opening_balance' => 'decimal:2',
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
    public function salesInvoices() { return $this->hasMany(SalesInvoice::class); }
    public function payments() { return $this->morphMany(Payment::class, 'party'); }
}
