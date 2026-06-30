<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Payment extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['payment_number', 'company_id', 'type', 'amount', 'mode', 'payment_date', 'party_type', 'party_id'])
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $event) => "Payment {$event}");
    }

    protected $fillable = [
        'uuid', 'company_id', 'payment_number', 'type', 'party_type', 'party_id',
        'amount', 'mode', 'reference', 'payment_date', 'notes', 'created_by',
    ];

    protected $casts = ['payment_date' => 'date:Y-m-d', 'amount' => 'decimal:2'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) $model->uuid = (string) Str::uuid();
        });
    }

    public function party() { return $this->morphTo(); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
