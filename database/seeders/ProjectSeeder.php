<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Project;
use App\Models\ProjectVersion;
use App\Models\AuditReport;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $issuer = User::where('role', 'issuer')->first();
        $auditor = User::where('role', 'auditor')->first(); // Mengambil data auditor

        /*
        ======================================
        PROJECT 1 — FULLY LISTED (LENGKAP DENGAN AUDIT)
        ======================================
        */

        $project1 = Project::create([
            'issuer_id' => $issuer->id
        ]);

        $v1 = ProjectVersion::create([
            'project_id' => $project1->id,
            'version_number' => 1,

            'name' => 'Solar Farm Surabaya',
            'description' => 'Pembangunan pembangkit listrik tenaga surya (PLTS) untuk mengurangi emisi karbon di area industri.',

            'location_country' => 'Indonesia',
            'location_province' => 'Jawa Timur',
            'location_city' => 'Surabaya',
            'address' => 'Kawasan Industri Rungkut',
            
            'project_type' => 'solar',

            // Data Teknis (Issuer Claim)
            'panel_capacity_wp' => 15000.00,
            'inverter_capacity_kw' => 15.00,
            'area_size_m2' => 120.50,
            'number_of_panels' => 30,
            'installation_date' => '2025-11-20',
            'installation_type' => 'Rooftop',
            'panel_brand' => 'Jinko Solar',
            'inverter_brand' => 'Growatt',

            'status' => 'listed',
            'admin_verification_status' => 'approved',
            'auditor_verification_status' => 'approved',
            'is_locked' => true,
            
            'auditor_id' => $auditor ? $auditor->id : null,
        ]);

        $project1->update([
            'active_version_id' => $v1->id
        ]);

        // Simulasi Laporan Audit untuk Project 1 dengan Struktur Baru (Periodik)
        if ($auditor) {
            
            // Kita simulasikan periode audit untuk Q4 2025
            $generationKwh = 5500.00;
            $emissionFactor = 0.00079; // Menggunakan standar faktor emisi PLN Jamali
            
            AuditReport::create([
                'project_version_id' => $v1->id,
                'auditor_id' => $auditor->id,
                
                // Tambahan Kolom Periode Baru
                'period_start' => '2025-10-01',
                'period_end' => '2025-12-31',
                
                'verified_installed_capacity_kwp' => 14850.00, // Sedikit beda dengan klaim
                'verified_generation_kwh' => $generationKwh, // Kolom baru pengganti annual
                'baseline_emission_factor' => $emissionFactor,
                
                // Kalkulasi otomatis kolom baru
                'carbon_reduction_amount_ton' => $generationKwh * $emissionFactor, 
                
                'onsite_measurement_date' => '2025-12-05',
                'audit_notes' => 'Kapasitas terverifikasi sedikit di bawah klaim karena shading parsial di pagi hari. Proyek sangat layak dan disetujui untuk periode Q4 2025.',
            ]);
        }


        /*
        ======================================
        PROJECT 2 — ADMIN REJECTED
        ======================================
        */

        $project2 = Project::create([
            'issuer_id' => $issuer->id
        ]);

        $v2 = ProjectVersion::create([
            'project_id' => $project2->id,
            'version_number' => 1,

            'name' => 'Mangrove Restoration',
            'description' => 'Need better documentation for the coastal area planted.',

            'location_country' => 'Indonesia',
            'location_province' => 'Jawa Timur',
            'location_city' => 'Gresik',
            'address' => 'Coastal Area',
            
            'project_type' => 'mangrove',

            // Mangrove tidak punya data teknis panel, jadi kita biarkan kosong/null untuk menguji UI
            'panel_capacity_wp' => null,

            'status' => 'rejected',
            'admin_verification_status' => 'rejected',
            'auditor_verification_status' => 'pending',
            'admin_notes' => 'Kalkulasi luasan lahan tidak sesuai dengan sertifikat tanah yang dilampirkan. Mohon direvisi.',
            'is_locked' => false
        ]);

        $project2->update([
            'active_version_id' => $v2->id
        ]);


        /*
        ======================================
        PROJECT 3 — WAITING AUDITOR
        ======================================
        */

        $project3 = Project::create([
            'issuer_id' => $issuer->id
        ]);

        $v3 = ProjectVersion::create([
            'project_id' => $project3->id,
            'version_number' => 1,

            'name' => 'Wind Turbine Project NTT',
            'description' => 'Pembangunan turbin angin skala menengah di area perbukitan Kupang.',

            'location_country' => 'Indonesia',
            'location_province' => 'NTT',
            'location_city' => 'Kupang',
            'address' => 'Bukit Angin Oesao',
            
            'project_type' => 'wind',

            // Data Teknis (Anggap saja ini spesifikasi turbin untuk testing UI)
            'panel_capacity_wp' => 50000.00, 
            'inverter_capacity_kw' => 50.00,
            'area_size_m2' => 500.00,
            'number_of_panels' => 5, // 5 Turbin
            'installation_date' => '2026-02-15',
            'installation_type' => 'Ground Mounted',
            'panel_brand' => 'Vestas',
            'inverter_brand' => 'Siemens',

            'status' => 'admin_approved',
            'admin_verification_status' => 'approved',
            'auditor_verification_status' => 'pending',
            'is_locked' => true
        ]);

        $project3->update([
            'active_version_id' => $v3->id
        ]);


        /*
        ======================================
        PROJECT 4 — AUDITOR REJECTED
        ======================================
        */

        $project4 = Project::create([
            'issuer_id' => $issuer->id
        ]);

        $v4 = ProjectVersion::create([
            'project_id' => $project4->id,
            'version_number' => 1,

            'name' => 'Floating Solar Mini Plant',
            'description' => 'Pemasangan panel surya mengapung di danau buatan.',

            'location_country' => 'Indonesia',
            'location_province' => 'Jawa Barat',
            'location_city' => 'Bandung',
            'address' => 'Danau Cileunca',
            
            'project_type' => 'solar',

            'panel_capacity_wp' => 8000.00,
            'inverter_capacity_kw' => 8.00,
            'area_size_m2' => 75.00,
            'number_of_panels' => 16,
            'installation_date' => '2026-03-01',
            'installation_type' => 'Floating',
            'panel_brand' => 'Canadian Solar',
            'inverter_brand' => 'Sungrow',

            'status' => 'rejected',
            'admin_verification_status' => 'approved',
            'auditor_verification_status' => 'rejected',
            'auditor_notes' => 'Sistem pelampung tidak memenuhi standar keamanan industri. Risiko kerusakan inverte akibat kelembapan sangat tinggi.',
            'is_locked' => false,
            
            'auditor_id' => $auditor ? $auditor->id : null,
        ]);

        $project4->update([
            'active_version_id' => $v4->id
        ]);


        /*
        ======================================
        PROJECT 5 — DRAFT (Data Belum Lengkap)
        ======================================
        */

        $project5 = Project::create([
            'issuer_id' => $issuer->id
        ]);

        $v5 = ProjectVersion::create([
            'project_id' => $project5->id,
            'version_number' => 1,

            'name' => 'Biochar Initiative Bali',
            'description' => 'Masih dalam tahap pengumpulan dokumen.',

            'location_country' => 'Indonesia',
            'location_province' => 'Bali',
            'location_city' => 'Denpasar',
            'address' => 'Agriculture Area',
            
            'project_type' => 'solar', // Default
            
            // Dibiarkan null karena baru sampai step 1
            'panel_capacity_wp' => null,

            'status' => 'draft',
            'admin_verification_status' => 'pending',
            'auditor_verification_status' => 'pending',
            'is_locked' => false
        ]);

        $project5->update([
            'active_version_id' => $v5->id
        ]);
    }
}