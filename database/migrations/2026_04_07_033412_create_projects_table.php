<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {

            $table->id();

            // OWNER
            $table->foreignId('issuer_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // ACTIVE VERSION
            $table->unsignedBigInteger('active_version_id')
                ->nullable();

            // BLOCKCHAIN FINAL DATA
            $table->string('blockchain_tx')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};