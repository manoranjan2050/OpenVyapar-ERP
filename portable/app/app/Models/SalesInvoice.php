<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class SalesInvoice extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['invoice_number', 'company_id', 'customer_id', 'total_amount', 'status', 'invoice_date'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $event) => "Sales Invoice {$event}");
    }

    protected $fillable = [
        'uuid', 'company_id', 'branch_id', 'financial_year_id', 'customer_id', 'created_by',
        'invoice_number', 'invoice_date', 'due_date', 'invoice_type', 'supply_type',
        'billing_address', 'shipping_address',
        'subtotal', 'discount_amount', 'taxable_amount',
        'cgst_amount', 'sgst_amount', 'igst_amount', 'cess_amount', 'round_off',
        'total_amount', 'paid_amount', 'balance_amount',
        'status', 'payment_mode', 'upi_ref', 'notes',
        'e_invoice_irn', 'e_way_bill_no',
    ];

    protected $casts = [
        'invoice_date' => 'date:Y-m-d',
        'due_date' => 'date:Y-m-d',
        'subtotal' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance_amount' => 'decimal:2',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) $model->uuid = (string) Str::uuid();
        });
    }

    public function company() { return $this->belongsTo(Company::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function financialYear() { return $this->belongsTo(FinancialYear::class); }
    public function items() { return $this->hasMany(SalesInvoiceItem::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
