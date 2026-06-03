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

            // LOKASI TERSTANDARISASI (Relasi ke tabel Wilayah)
            $table->string('kode_provinsi', 2);
            $table->string('kode_kota', 5);
            $table->string('kode_kecamatan', 8);
            $table->string('kode_kelurahan', 13);
            $table->text('address');

            $table->string('project_type')->default('solar');

            // TECHNICAL SPECIFICATIONS (STEP 2)
            $table->decimal('total_system_capacity_kwp', 10, 2)->nullable();
            $table->decimal('inverter_capacity_kw', 10, 2)->nullable();
            $table->date('installation_date')->nullable();
            $table->string('panel_brand')->nullable();
            $table->string('inverter_brand')->nullable();

            // CLAIM PERIOD (Diusulkan oleh Issuer)
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();

            // VERIFICATION FLOW
            $table->enum('status',[
                'draft',
                'submitted',
                'admin_approved',
                'auditor_verified',
                'returned_to_auditor', // 👉 NEW: Status ditolak oleh Admin untuk direvisi Auditor
                'rejected',
                'listed'
            ])->default('draft');

            $table->enum('admin_verification_status',[
                'pending','approved','rejected'
            ])->default('pending');

            $table->enum('auditor_verification_status',[
                'pending','approved','revision','rejected' // 👉 NEW: Status 'revision' ditambahkan
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