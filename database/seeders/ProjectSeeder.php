<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Project;
use App\Models\ProjectVersion;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $issuer = User::where('role','issuer')->first();

        /*
        ======================================
        PROJECT 1 — FULLY LISTED
        ======================================
        */

        $project1 = Project::create([
            'issuer_id' => $issuer->id
        ]);

        $v1 = ProjectVersion::create([
            'project_id'=>$project1->id,
            'version_number'=>1,

            'name'=>'Solar Farm Surabaya',
            'description'=>'Carbon reduction solar plant',

            'location_country'=>'Indonesia',
            'location_province'=>'Jawa Timur',
            'location_city'=>'Surabaya',
            'address'=>'Kapasari',

            'status'=>'listed',
            'admin_verification_status'=>'approved',
            'auditor_verification_status'=>'approved',
            'is_locked'=>true
        ]);

        $project1->update([
            'active_version_id'=>$v1->id
        ]);



        /*
        ======================================
        PROJECT 2 — ADMIN REJECTED
        ======================================
        */

        $project2 = Project::create([
            'issuer_id'=>$issuer->id
        ]);

        $v2 = ProjectVersion::create([
            'project_id'=>$project2->id,
            'version_number'=>1,

            'name'=>'Mangrove Restoration',
            'description'=>'Need better documentation',

            'location_country'=>'Indonesia',
            'location_province'=>'Jawa Timur',
            'location_city'=>'Gresik',
            'address'=>'Coastal Area',

            'status'=>'rejected',
            'admin_verification_status'=>'rejected',
            'auditor_verification_status'=>'pending',
            'admin_notes'=>'Emission calculation invalid',
            'is_locked'=>false
        ]);

        $project2->update([
            'active_version_id'=>$v2->id
        ]);



        /*
        ======================================
        PROJECT 3 — WAITING AUDITOR
        ======================================
        */

        $project3 = Project::create([
            'issuer_id'=>$issuer->id
        ]);

        $v3 = ProjectVersion::create([
            'project_id'=>$project3->id,
            'version_number'=>1,

            'name'=>'Wind Turbine Project',
            'description'=>'Awaiting auditor verification',

            'location_country'=>'Indonesia',
            'location_province'=>'NTT',
            'location_city'=>'Kupang',
            'address'=>'Wind Area',

            'status'=>'admin_approved',
            'admin_verification_status'=>'approved',
            'auditor_verification_status'=>'pending',
            'is_locked'=>true
        ]);

        $project3->update([
            'active_version_id'=>$v3->id
        ]);



        /*
        ======================================
        PROJECT 4 — AUDITOR REJECTED
        ======================================
        */

        $project4 = Project::create([
            'issuer_id'=>$issuer->id
        ]);

        $v4 = ProjectVersion::create([
            'project_id'=>$project4->id,
            'version_number'=>1,

            'name'=>'Hydro Mini Plant',
            'description'=>'Auditor rejected case',

            'location_country'=>'Indonesia',
            'location_province'=>'Jawa Barat',
            'location_city'=>'Bandung',
            'address'=>'River Zone',

            'status'=>'rejected',
            'admin_verification_status'=>'approved',
            'auditor_verification_status'=>'rejected',
            'auditor_notes'=>'Measurement method invalid',
            'is_locked'=>false
        ]);

        $project4->update([
            'active_version_id'=>$v4->id
        ]);



        /*
        ======================================
        PROJECT 5 — DRAFT
        ======================================
        */

        $project5 = Project::create([
            'issuer_id'=>$issuer->id
        ]);

        $v5 = ProjectVersion::create([
            'project_id'=>$project5->id,
            'version_number'=>1,

            'name'=>'Biochar Initiative',
            'description'=>'Still editing',

            'location_country'=>'Indonesia',
            'location_province'=>'Bali',
            'location_city'=>'Denpasar',
            'address'=>'Agriculture Area',

            'status'=>'draft',
            'admin_verification_status'=>'pending',
            'auditor_verification_status'=>'pending',
            'is_locked'=>false
        ]);

        $project5->update([
            'active_version_id'=>$v5->id
        ]);
    }
}