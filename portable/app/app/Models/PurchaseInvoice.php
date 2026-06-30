<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class PurchaseInvoice extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['invoice_number', 'company_id', 'supplier_id', 'total_amount', 'status', 'invoice_date'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $event) => "Purchase Invoice {$event}");
    }

    protected $fillable = [
        'uuid', 'company_id', 'branch_id', 'financial_year_id', 'supplier_id', 'created_by',
        'invoice_number', 'supplier_invoice_number', 'invoice_date', 'due_date',
        'subtotal', 'discount_amount', 'taxable_amount',
        'cgst_amount', 'sgst_amount', 'igst_amount',
        'total_amount', 'paid_amount', 'balance_amount',
        'status', 'notes',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) $model->uuid = (string) Str::uuid();
        });
    }

    public function company() { return $this->belongsTo(Company::class); }
    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function financialYear() { return $this->belongsTo(FinancialYear::class); }
    public function items() { return $this->hasMany(PurchaseInvoiceItem::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
