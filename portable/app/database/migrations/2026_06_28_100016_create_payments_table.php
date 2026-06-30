<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('payment_number');
            $table->enum('type', ['received', 'made']); // received=customer, made=supplier
            $table->string('party_type'); // Customer | Supplier
            $table->unsignedBigInteger('party_id');
            $table->decimal('amount', 14, 2);
            $table->enum('mode', ['cash', 'upi', 'bank_transfer', 'cheque', 'card'])->default('cash');
            $table->string('reference')->nullable(); // UPI ref / cheque no
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['party_type', 'party_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
