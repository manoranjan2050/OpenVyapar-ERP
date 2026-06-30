<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Challan extends Model
{
    use SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['challan_number', 'company_id', 'customer_id', 'status', 'challan_date'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $event) => "Challan {$event}");
    }

    protected $fillable = [
        'uuid', 'challan_number', 'challan_date', 'company_id',
        'customer_id', 'items', 'transporter', 'vehicle_no',
        'notes', 'status', 'sales_invoice_id',
    ];

    protected $casts = [
        'items' => 'array',
        'challan_date' => 'date',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($m) {
            if (empty($m->uuid)) $m->uuid = (string) Str::uuid();
        });
    }

    public function customer()  { return $this->belongsTo(Customer::class); }
    public function company()   { return $this->belongsTo(Company::class); }
    public function salesInvoice() { return $this->belongsTo(SalesInvoice::class); }

    public static function nextNumber(int $companyId): string
    {
        $year = now()->month >= 4 ? now()->year : now()->year - 1;
        $suffix = substr($year, 2) . substr($year + 1, 2); // "2526"
        $last = static::where('company_id', $companyId)
            ->where('challan_number', 'like', "DC-{$suffix}-%")
            ->max('challan_number');
        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;
        return sprintf('DC-%s-%04d', $suffix, $seq);
    }
}
