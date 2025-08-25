<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('backup_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('database_connection_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('profile_name')->nullable();
            $table->string('backup_path')->nullable();
            $table->enum('status', ['pending', 'running', 'completed', 'failed']);
            $table->enum('mechanism', ['manual', 'automated'])->default('manual');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->text('error_message')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_jobs');
    }
};
