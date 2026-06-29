<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTransaction extends Model
{
    protected $fillable = [
        'company_id', 'product_id', 'warehouse_id', 'type', 'quantity',
        'balance_after', 'rate', 'reference_type', 'reference_id', 'note',
        'created_by', 'transacted_at',
    ];

    protected $casts = ['transacted_at' => 'datetime', 'rate' => 'decimal:2'];

    public function product() { return $this->belongsTo(Product::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function reference() { return $this->morphTo(); }
}
