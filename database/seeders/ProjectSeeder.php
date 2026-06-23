<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Project;
use App\Models\ProjectVersion;
use App\Models\ProjectDocument;
use Carbon\Carbon;

class ProjectSeeder extends Seeder
{
    public function run()
    {
        $issuerId = 3; 

        $projectsData = [
            [
                'name' => 'PLTS Atap Pabrik Garmen',
                'description' => 'Instalasi panel surya atap untuk kebutuhan listrik mandiri pabrik garmen di kawasan industri.',
                'kode_provinsi' => '34', 
                'kode_kota' => '34.01',   
                'kode_kecamatan' => '34.01.01',
                'kode_kelurahan' => '34.01.01.2001',
                'address' => 'Jl. Wates Km 15, Sentolo',
                'project_type' => 'solar',
                'total_system_capacity_kwp' => 150.5,
                'inverter_capacity_kw' => 120.0,
                'installation_date' => Carbon::now()->subMonths(8)->format('Y-m-d'),
                'panel_brand' => 'Canadian Solar',
                'inverter_brand' => 'Huawei',
                'period_start' => Carbon::now()->subMonths(6)->format('Y-m-d'),
                'period_end' => Carbon::now()->subMonths(1)->format('Y-m-d'),
                'image' => 'projects/images/gambar1.jpg',
                'document' => 'projects/legal_docs/dokumen1.pdf',
            ],
            [
                'name' => 'PLTS Ground Mounted Desa Maju',
                'description' => 'Proyek PLTS skala desa untuk elektrifikasi fasilitas umum dan pompa air pertanian.',
                'kode_provinsi' => '32', 
                'kode_kota' => '32.04',   
                'kode_kecamatan' => '32.04.01',
                'kode_kelurahan' => '32.04.01.2001',
                'address' => 'Desa Margamulya, Pangalengan',
                'project_type' => 'solar',
                'total_system_capacity_kwp' => 300.0,
                'inverter_capacity_kw' => 250.0,
                'installation_date' => Carbon::now()->subMonths(12)->format('Y-m-d'),
                'panel_brand' => 'Jinko Solar',
                'inverter_brand' => 'SMA Solar',
                'period_start' => Carbon::now()->subMonths(10)->format('Y-m-d'),
                'period_end' => Carbon::now()->subMonths(2)->format('Y-m-d'),
                'image' => 'projects/images/gambar2.jpg',
                'document' => 'projects/legal_docs/dokumen2.pdf',
            ],
            [
                'name' => 'PLTS Rooftop Gedung Perkantoran',
                'description' => 'Pemasangan PLTS atap untuk mengurangi jejak karbon gedung perkantoran pusat kota.',
                'kode_provinsi' => '31', 
                'kode_kota' => '31.73',   
                'kode_kecamatan' => '31.73.01',
                'kode_kelurahan' => '31.73.01.1001',
                'address' => 'Jl. Daan Mogot Km 10',
                'project_type' => 'solar',
                'total_system_capacity_kwp' => 80.0,
                'inverter_capacity_kw' => 60.0,
                'installation_date' => Carbon::now()->subMonths(5)->format('Y-m-d'),
                'panel_brand' => 'Trina Solar',
                'inverter_brand' => 'Sungrow',
                'period_start' => Carbon::now()->subMonths(4)->format('Y-m-d'),
                'period_end' => Carbon::now()->subMonths(1)->format('Y-m-d'),
                'image' => 'projects/images/gambar3.jpg',
                'document' => 'projects/legal_docs/dokumen3.pdf',
            ]
        ];

        foreach ($projectsData as $index => $data) {
            // 👉 KUNCI PERBAIKAN: Instansiasi manual untuk memaksa ID melompat ke angka 6
            $project = new Project();
            $project->id = 9 + $index; // Looping 1: ID = 6, Looping 2: ID = 7, Looping 3: ID = 8
            $project->issuer_id = $issuerId;
            $project->save();

            $version = ProjectVersion::create([
                'project_id' => $project->id, // Otomatis mengikat ke ID 6, 7, atau 8
                'version_number' => 1,
                'name' => $data['name'],
                'description' => $data['description'],
                'kode_provinsi' => $data['kode_provinsi'],
                'kode_kota' => $data['kode_kota'],
                'kode_kecamatan' => $data['kode_kecamatan'],
                'kode_kelurahan' => $data['kode_kelurahan'],
                'address' => $data['address'],
                'project_type' => $data['project_type'],
                'status' => 'draft', 
                'total_system_capacity_kwp' => $data['total_system_capacity_kwp'],
                'inverter_capacity_kw' => $data['inverter_capacity_kw'],
                'installation_date' => $data['installation_date'],
                'panel_brand' => $data['panel_brand'],
                'inverter_brand' => $data['inverter_brand'],
                'period_start' => $data['period_start'],
                'period_end' => $data['period_end'],
                'is_locked' => false,
            ]);

            $project->update(['active_version_id' => $version->id]);

            ProjectDocument::create([
                'project_version_id' => $version->id,
                'type' => 'image',
                'original_filename' => 'gambar' . ($index + 1) . '.jpg',
                'file_path' => $data['image'],
                'uploader_role' => 'issuer',
            ]);

            ProjectDocument::create([
                'project_version_id' => $version->id,
                'type' => 'document',
                'original_filename' => 'dokumen' . ($index + 1) . '.pdf',
                'file_path' => $data['document'],
                'uploader_role' => 'issuer',
            ]);
        }
    }
}