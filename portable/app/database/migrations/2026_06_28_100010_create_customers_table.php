<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('gstin', 15)->nullable();
            $table->string('pan', 10)->nullable();
            $table->text('billing_address')->nullable();
            $table->string('billing_city')->nullable();
            $table->string('billing_state')->nullable();
            $table->string('billing_pincode', 10)->nullable();
            $table->text('shipping_address')->nullable();
            $table->decimal('credit_limit', 12, 2)->default(0);
            $table->integer('credit_days')->default(0);
            $table->decimal('opening_balance', 12, 2)->default(0);
            $table->enum('opening_balance_type', ['debit', 'credit'])->default('debit');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'phone']);
            $table->index(['company_id', 'gstin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
