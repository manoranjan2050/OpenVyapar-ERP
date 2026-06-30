<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');       // Kilogram
            $table->string('short_name'); // KG
            $table->timestamps();
            $table->unique(['company_id', 'short_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
