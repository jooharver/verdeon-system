<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_reports', function (Blueprint $table) {
            $table->id();
            
            // Relasi ke versi proyek yang diaudit
            $table->foreignId('project_version_id')
                  ->constrained()
                  ->cascadeOnDelete();
                  
            // Relasi ke auditor yang melakukan tugas
            $table->foreignId('auditor_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // Data Teknis Terverifikasi
            $table->decimal('verified_installed_capacity_kwp', 10, 2);
            $table->decimal('verified_annual_generation_kwh', 15, 2);
            $table->decimal('baseline_emission_factor', 8, 4);
            $table->decimal('expected_carbon_reduction_ton_per_year', 15, 2);
            $table->date('onsite_measurement_date');
            
            // Keputusan & Catatan
            $table->text('audit_notes')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_reports');
    }
};