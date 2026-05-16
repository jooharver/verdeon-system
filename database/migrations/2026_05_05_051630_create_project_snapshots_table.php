<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('project_version_id')->constrained()->onDelete('cascade');
            $table->string('status_at_snapshot'); // misal: 'admin_approved', 'auditor_verified', 'listed'
            $table->json('snapshot_data'); // Menyimpan format JSON raw
            $table->string('data_hash'); // Menyimpan hasil SHA-256 dari snapshot_data
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_snapshots');
    }
};