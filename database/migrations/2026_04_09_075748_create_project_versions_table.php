<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_versions', function (Blueprint $table) {

            $table->id();

            $table->foreignId('project_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->integer('version_number');

            // PROJECT DATA (STEP 1)
            $table->string('name');
            $table->text('description')->nullable();

            $table->string('location_country');
            $table->string('location_province');
            $table->string('location_city');
            $table->text('address');

            $table->string('project_type')->default('solar');

            // TECHNICAL SPECIFICATIONS (STEP 2) - BISA KOSONG (NULLABLE)
            $table->decimal('panel_capacity_wp', 12, 2)->nullable();
            $table->decimal('inverter_capacity_kw', 10, 2)->nullable();
            $table->decimal('area_size_m2', 10, 2)->nullable();
            $table->integer('number_of_panels')->nullable();
            $table->date('installation_date')->nullable();
            $table->string('installation_type')->nullable();
            $table->string('panel_brand')->nullable();
            $table->string('inverter_brand')->nullable();

            // VERIFICATION FLOW
            $table->enum('status',[
                'draft',
                'submitted',
                'admin_approved',
                'auditor_verified',
                'rejected',
                'listed'
            ])->default('draft');

            $table->enum('admin_verification_status',[
                'pending','approved','rejected'
            ])->default('pending');

            $table->enum('auditor_verification_status',[
                'pending','approved','rejected'
            ])->default('pending');

            $table->boolean('is_locked')->default(false);

            $table->foreignId('auditor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('admin_notes')->nullable();
            $table->text('auditor_notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_versions');
    }
};