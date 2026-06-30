<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialYear extends Model
{
    protected $fillable = ['company_id', 'name', 'start_date', 'end_date', 'is_current', 'is_locked'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
        'is_locked' => 'boolean',
    ];

    public function company() { return $this->belongsTo(Company::class); }
    public function salesInvoices() { return $this->hasMany(SalesInvoice::class); }
    public function purchaseInvoices() { return $this->hasMany(PurchaseInvoice::class); }
}
