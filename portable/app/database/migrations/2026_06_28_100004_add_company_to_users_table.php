<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->unique()->after('id');
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete()->after('uuid');
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete()->after('company_id');
            $table->string('phone')->nullable()->after('email');
            $table->boolean('is_active')->default(true)->after('phone');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['uuid', 'company_id', 'branch_id', 'phone', 'is_active']);
            $table->dropSoftDeletes();
        });
    }
};
