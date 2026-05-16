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
            
            $table->foreignId('project_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('auditor_id')->constrained('users')->cascadeOnDelete();

            // Kolom period_start dan period_end DIHAPUS dari sini

            // Transparansi Metode Kalkulasi
            $table->enum('calculation_method', [
                'system_estimated', 
                'actual_inverter'
            ])->default('system_estimated');
            
            // Data Teknis Terverifikasi
            $table->decimal('verified_installed_capacity_kwp', 10, 2);
            $table->decimal('verified_generation_kwh', 15, 2); 
            $table->decimal('baseline_emission_factor', 8, 4);
            
            // Kalkulasi Otomatis
            $table->decimal('carbon_reduction_amount_ton', 15, 2); 
            
            // Checklist Verifikasi Auditor
            $table->json('verification_checklist')->nullable();
            
            // Opsional jika metode estimasi tidak butuh cek lapangan
            $table->date('onsite_measurement_date')->nullable();
            $table->text('audit_notes')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_reports');
    }
};