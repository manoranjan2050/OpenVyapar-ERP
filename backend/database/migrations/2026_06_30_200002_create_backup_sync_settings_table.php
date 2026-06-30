<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_sync_providers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('provider'); // email, local, google_drive, dropbox, onedrive, github
            $table->boolean('enabled')->default(false);
            $table->json('config')->nullable(); // provider-specific settings
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_sync_status')->nullable(); // success | error
            $table->text('last_sync_message')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_sync_providers');
    }
};
