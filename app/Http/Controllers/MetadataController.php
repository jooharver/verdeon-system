<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\ProjectVersion;

class MetadataController extends Controller
{
    // ==========================================
    // BLOCKCHAIN: GENERATE METADATA & HASH
    // ==========================================
    public function generateMetadata(Request $request, $projectId, $versionId)
    {
        try {
            $project = Project::with('issuer')->findOrFail($projectId);
            
            $version = ProjectVersion::with(['documents', 'auditReport.auditor', 'provinsi', 'kota'])
                ->where('project_id', $projectId)
                ->findOrFail($versionId);

            $currentStatus = $request->query('status', $version->status);

            $locationName = '';
            if ($version->kota && $version->provinsi) {
                $locationName = $version->kota->nama . ', ' . $version->provinsi->nama;
            } else {
                $locationName = $version->kode_kota ?? 'Unknown Location';
            }

            // --- 1. LOGIKA ARRAY HARUS 100% SAMA DENGAN ProjectController ---
            $attributes = [
                ["trait_type" => "Project ID", "value" => $project->id],
                ["trait_type" => "Version", "value" => $version->version_number],
                ["trait_type" => "Status", "value" => $currentStatus], 
                ["trait_type" => "Issuer", "value" => $project->issuer->name ?? "Issuer"],
                ["trait_type" => "Location", "value" => $locationName],
                ["trait_type" => "Project Type", "value" => $version->project_type ?? "solar"],
                ["trait_type" => "Total Capacity (kWp)", "value" => (string)$version->total_system_capacity_kwp],
            ];

            // Masukkan data auditor jika statusnya mencukupi
            if (in_array($currentStatus, ['auditor_verified', 'listed']) && $version->auditReport) {
                $audit = $version->auditReport;
                $attributes[] = ["trait_type" => "Auditor", "value" => $audit->auditor->name ?? "Auditor"];
                
                $methodName = $audit->calculation_method === 'system_estimated' ? 'Conservative System Estimation' : 'Actual Inverter Data';
                $attributes[] = ["trait_type" => "Calculation Method", "value" => $methodName];
                
                $attributes[] = ["trait_type" => "Verified Capacity (kWp)", "value" => (string)$audit->verified_installed_capacity_kwp];
                $attributes[] = ["trait_type" => "Verified Generation (kWh)", "value" => (string)$audit->verified_generation_kwh];
                $attributes[] = ["trait_type" => "Carbon Reduction (Ton)", "value" => (string)$audit->carbon_reduction_amount_ton];
                
                // 👉 UPDATE: Ambil date dari projectVersion
                $attributes[] = ["trait_type" => "Period Start", "value" => $version->period_start ? $version->period_start->format('Y-m-d') : '-'];
                $attributes[] = ["trait_type" => "Period End", "value" => $version->period_end ? $version->period_end->format('Y-m-d') : '-'];
            }

            $images = [];
            $documents = [];
            $mainImage = null;

            if ($version->documents) {
                foreach ($version->documents as $doc) {
                    $url = asset('storage/' . $doc->file_path); 
                    if ($doc->type === 'image') {
                        if (!$mainImage) $mainImage = $url;
                        $images[] = [
                            "name" => $doc->original_filename,
                            "url" => $url,
                            "role" => $doc->uploader_role
                        ];
                    } else {
                        $documents[] = [
                            "name" => $doc->original_filename,
                            "url" => $url,
                            "role" => $doc->uploader_role
                        ];
                    }
                }
            }

            $metadata = [
                "name" => "Verideon Project: " . $version->name,
                "description" => $version->description ?? "",
                "image" => $mainImage ?? "",
                "attributes" => $attributes,
                "images" => $images,
                "documents" => $documents
            ];

            // --- 2. FORMAT JSON KONSISTEN (SAMA PERSIS DENGAN SNAPSHOT) ---
            $jsonString = json_encode(["metadata" => $metadata], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            // --- 3. HASHING KONSISTEN ---
            $dataHash = hash('sha256', $jsonString);

            return response()->json([
                'metadata' => $metadata, 
                'tokenURI' => url("/api/projects/{$projectId}/versions/{$versionId}/metadata"), 
                'dataHash' => $dataHash, 
            ], 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}