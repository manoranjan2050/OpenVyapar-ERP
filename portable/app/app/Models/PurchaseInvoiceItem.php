<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseInvoiceItem extends Model
{
    protected $fillable = [
        'purchase_invoice_id', 'product_id', 'product_name', 'hsn_code',
        'quantity', 'unit', 'rate', 'discount_pct', 'discount_amount', 'taxable_amount',
        'gst_rate', 'cgst_rate', 'sgst_rate', 'igst_rate',
        'cgst_amount', 'sgst_amount', 'igst_amount', 'total_amount',
    ];

    protected $casts = ['quantity' => 'decimal:3', 'rate' => 'decimal:2', 'total_amount' => 'decimal:2'];

    public function purchaseInvoice() { return $this->belongsTo(PurchaseInvoice::class); }
    public function product() { return $this->belongsTo(Product::class); }
}
