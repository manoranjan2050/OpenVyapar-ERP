<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('challan_number', 50)->unique();
            $table->date('challan_date');
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->json('items');
            $table->string('transporter')->nullable();
            $table->string('vehicle_no', 30)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('draft'); // draft|dispatched|converted|cancelled
            $table->foreignId('sales_invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challans');
    }
};
