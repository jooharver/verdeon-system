<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\ProjectDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\ProjectVersion;
use App\Models\AuditReport;
use App\Models\ProjectSnapshot; 

class ProjectController extends Controller
{
    // ==========================================
    // HELPER: GENERATE METADATA KONSISTEN
    // ==========================================
    private function generateMetadataArray($project, $version)
    {
        $locationName = '';
        if ($version->kota && $version->provinsi) {
            $locationName = $version->kota->nama . ', ' . $version->provinsi->nama;
        } else {
            $locationName = $version->kode_kota ?? 'Unknown Location';
        }

        $attributes = [
            ["trait_type" => "Project ID", "value" => $project->id],
            ["trait_type" => "Version", "value" => $version->version_number],
            ["trait_type" => "Status", "value" => $version->status],
            ["trait_type" => "Issuer", "value" => $project->issuer->name ?? "Issuer"],
            ["trait_type" => "Location", "value" => $locationName],
            ["trait_type" => "Project Type", "value" => $version->project_type ?? "solar"],
            ["trait_type" => "Total Capacity (kWp)", "value" => (string)$version->total_system_capacity_kwp],
        ];

        if (in_array($version->status, ['auditor_verified', 'listed']) && $version->auditReport) {
            $audit = $version->auditReport;
            $attributes[] = ["trait_type" => "Auditor", "value" => $audit->auditor->name ?? "Auditor"];
            
            $methodName = $audit->calculation_method === 'system_estimated' ? 'Conservative System Estimation' : 'Actual Inverter Data';
            $attributes[] = ["trait_type" => "Calculation Method", "value" => $methodName];
            
            $attributes[] = ["trait_type" => "Verified Capacity (kWp)", "value" => (string)$audit->verified_installed_capacity_kwp];
            $attributes[] = ["trait_type" => "Verified Generation (kWh)", "value" => (string)$audit->verified_generation_kwh];
            $attributes[] = ["trait_type" => "Carbon Reduction (Ton)", "value" => (string)$audit->carbon_reduction_amount_ton];
            
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

        return [
            "name" => "Verideon Project: " . $version->name,
            "description" => $version->description ?? "",
            "image" => $mainImage ?? "",
            "attributes" => $attributes,
            "images" => $images,
            "documents" => $documents
        ];
    }

    private function saveSnapshot($project, $version, $status)
    {
        $project->loadMissing(['issuer']);
        $version->loadMissing(['documents', 'auditReport.auditor', 'provinsi', 'kota']);

        $metadataArray = $this->generateMetadataArray($project, $version);
        $jsonString = json_encode(["metadata" => $metadataArray], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $dataHash = hash('sha256', $jsonString);

        ProjectSnapshot::create([
            'project_id' => $project->id,
            'project_version_id' => $version->id,
            'status_at_snapshot' => $status,
            'snapshot_data' => ["metadata" => $metadataArray],
            'data_hash' => $dataHash
        ]);

        return $dataHash;
    }
    
    //GET ALL PROJECTS YANG SUDAH LISTING DI MARKETPLACE (UNTUK DITAMPILKAN DI MARKETPLACE TANPA MEMPEDULIKAN ROLE)
    public function getMarketProjects()
    {
        // Mengambil proyek yang statusnya 'listed' saja
        $projects = Project::with([
            'issuer', 
            'activeVersion.documents', 
            'activeVersion.auditReport.auditor',
            'activeVersion.provinsi',
            'activeVersion.kota'
        ])
        ->whereHas('activeVersion', function ($q) {
            $q->where('status', 'listed');
        })
        ->latest()
        ->get();

        return response()->json($projects);
    }

    // ===============================
    // ISSUER CREATE PROJECT
    // ===============================
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'kode_provinsi' => 'required|string|max:2',
            'kode_kota' => 'required|string|max:5',
            'kode_kecamatan' => 'required|string|max:8',
            'kode_kelurahan' => 'required|string|max:13',
            'address' => 'required',
            'project_images.*' => 'nullable|image|max:5120',
            'project_documents.*' => 'nullable|mimes:pdf|max:10240',
            'total_system_capacity_kwp' => 'nullable|numeric',
            'inverter_capacity_kw' => 'nullable|numeric',
            'installation_date' => 'nullable|date',
            'panel_brand' => 'nullable|string',
            'inverter_brand' => 'nullable|string',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date|after:period_start', 
        ]);

        DB::beginTransaction();
        try {
            $project = Project::create(['issuer_id' => Auth::id()]);

            $version = ProjectVersion::create([
                'project_id' => $project->id,
                'version_number' => 1,
                'name' => $request->name,
                'description' => $request->description,
                'kode_provinsi' => $request->kode_provinsi,
                'kode_kota' => $request->kode_kota,
                'kode_kecamatan' => $request->kode_kecamatan,
                'kode_kelurahan' => $request->kode_kelurahan,
                'address' => $request->address,
                'status' => 'draft',
                'total_system_capacity_kwp' => $request->total_system_capacity_kwp,
                'inverter_capacity_kw' => $request->inverter_capacity_kw,
                'installation_date' => $request->installation_date,
                'panel_brand' => $request->panel_brand,
                'inverter_brand' => $request->inverter_brand,
                'period_start' => $request->period_start,
                'period_end' => $request->period_end,
            ]);

            $project->update(['active_version_id' => $version->id]);
            $this->uploadProjectFiles($request, $version->id, 'issuer');

            DB::commit();
            return response()->json(['message' => 'Project V1 & Documents created', 'project' => $project]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'kode_provinsi' => 'sometimes|required|string|max:2',
            'kode_kota' => 'sometimes|required|string|max:5',
            'kode_kecamatan' => 'sometimes|required|string|max:8',
            'kode_kelurahan' => 'sometimes|required|string|max:13',
            'address' => 'sometimes|required|string',
            'project_type' => 'nullable|string',
            'description' => 'nullable|string',
            'project_images.*' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'project_documents.*' => 'nullable|mimes:pdf,doc,docx|max:10240',
            'total_system_capacity_kwp' => 'nullable|numeric',
            'inverter_capacity_kw' => 'nullable|numeric',
            'installation_date' => 'nullable|date',
            'panel_brand' => 'nullable|string',
            'inverter_brand' => 'nullable|string',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date|after:period_start', 
        ]);

        $project = Project::with('activeVersion')->findOrFail($id);
        $version = $project->activeVersion;

        if ($project->issuer_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!in_array($version->status, ['draft', 'revision'])) {
            return response()->json(['message' => 'Hanya proyek berstatus Draft yang dapat diedit.'], 400);
        }

        DB::beginTransaction();
        try {
            $version->update($request->only([
                'name', 'description', 
                'kode_provinsi', 'kode_kota', 'kode_kecamatan', 'kode_kelurahan', 
                'address', 'project_type', 'total_system_capacity_kwp', 
                'inverter_capacity_kw', 'installation_date', 
                'panel_brand', 'inverter_brand', 'period_start', 'period_end'
            ]));

            $this->uploadProjectFiles($request, $version->id, 'issuer');

            DB::commit();
            return response()->json([
                'message' => 'Draft project updated successfully', 
                'version' => $version
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function reviseProject($id)
    {
        $project = Project::with('activeVersion.documents')->findOrFail($id);
        $oldVersion = $project->activeVersion;

        if ($oldVersion->status !== 'rejected') {
            return response()->json(['message' => 'Hanya proyek yang ditolak (rejected) yang dapat direvisi.'], 400);
        }

        DB::beginTransaction();
        try {
            $newVersion = ProjectVersion::create([
                'project_id' => $project->id,
                'version_number' => $oldVersion->version_number + 1,
                'name' => $oldVersion->name,
                'description' => $oldVersion->description,
                'kode_provinsi' => $oldVersion->kode_provinsi,
                'kode_kota' => $oldVersion->kode_kota,
                'kode_kecamatan' => $oldVersion->kode_kecamatan,
                'kode_kelurahan' => $oldVersion->kode_kelurahan,
                'address' => $oldVersion->address,
                'project_type' => $oldVersion->project_type,
                'status' => 'draft',
                'total_system_capacity_kwp' => $oldVersion->total_system_capacity_kwp,
                'inverter_capacity_kw' => $oldVersion->inverter_capacity_kw,
                'installation_date' => $oldVersion->installation_date,
                'panel_brand' => $oldVersion->panel_brand,
                'inverter_brand' => $oldVersion->inverter_brand,
                'period_start' => $oldVersion->period_start,
                'period_end' => $oldVersion->period_end,
            ]);

            if ($oldVersion->documents) {
                foreach ($oldVersion->documents as $doc) {
                    ProjectDocument::create([
                        'project_version_id' => $newVersion->id,
                        'type' => $doc->type,
                        'original_filename' => $doc->original_filename,
                        'file_path' => $doc->file_path,
                        'uploader_role' => $doc->uploader_role
                    ]);
                }
            }

            $project->update(['active_version_id' => $newVersion->id]);

            DB::commit();
            return response()->json([
                'message' => 'Revision version created successfully. Documents carried over.',
                'new_version_id' => $newVersion->id
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function uploadProjectFiles(Request $request, $versionId, $role)
    {
        if ($request->hasFile('project_images')) {
            foreach ($request->file('project_images') as $file) {
                $path = $file->store('projects/images', 'public');
                ProjectDocument::create([
                    'project_version_id' => $versionId,
                    'type' => 'image',
                    'original_filename' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'uploader_role' => $role
                ]);
            }
        }

        if ($request->hasFile('project_documents')) {
            foreach ($request->file('project_documents') as $file) {
                $path = $file->store('projects/legal_docs', 'public');
                ProjectDocument::create([
                    'project_version_id' => $versionId,
                    'type' => 'document',
                    'original_filename' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'uploader_role' => $role
                ]);
            }
        }
    }

    public function submit($id)
    {
        $project = Project::findOrFail($id);
        $version = $project->activeVersion;

        if ($project->issuer_id !== auth()->id()) {
            return response()->json(['message'=>'Unauthorized'],403);
        }

        if ($version->status !== 'draft') {
            return response()->json(['message'=>'Already submitted'],400);
        }

        if (!$version->period_start || !$version->period_end) {
            return response()->json(['message' => 'Claim Period (Start & End Date) harus diisi sebelum disubmit.'], 400);
        }

        $version->update([
            'status'=>'submitted',
            'is_locked'=>true
        ]);

        $dataHash = $this->saveSnapshot($project, $version, 'submitted');

        return response()->json([
            'message'=>'Version submitted',
            'version'=>$version,
            'dataHash'=>$dataHash
        ]);
    }

    public function issuerProjects()
    {
        $projects = Project::with([
            'issuer',
            'activeVersion.documents', 
            'activeVersion.auditReport.auditor',
            'activeVersion.provinsi', 
            'activeVersion.kota',
            'activeVersion.kecamatan', 
            'activeVersion.kelurahan', 
            'versions' => function($query) {
                $query->orderBy('version_number', 'desc');
            }
        ])
        ->where('issuer_id', auth()->id())
        ->latest()
        ->get();

        return response()->json($projects);
    }

    public function show($id)
    {
        $project = Project::with([
            'issuer', 
            'activeVersion.documents', 
            'activeVersion.auditReport.auditor',
            'activeVersion.provinsi', 
            'activeVersion.kota',
            'activeVersion.kecamatan',
            'activeVersion.kelurahan'
        ])
        ->findOrFail($id);

        $user = auth()->user();

        if ($user->role === 'issuer' && $project->issuer_id !== $user->id) {
            return response()->json(['message'=>'Unauthorized'],403);
        }

        return response()->json([
            'project'=>$project,
            'active_version'=>$project->activeVersion
        ]);
    }

    public function versions($id)
    {
        $project = Project::with('versions')->findOrFail($id);
        $user = auth()->user();

        if ($user->role === 'issuer' && $project->issuer_id !== $user->id) {
            return response()->json(['message'=>'Unauthorized'],403);
        }

        return response()->json([
            'project_id'=>$project->id,
            'versions'=>$project->versions->sortByDesc('version_number')->values()
        ]);
    }

    public function showVersion($projectId, $versionId)
    {
        $project = Project::findOrFail($projectId);
        $version = $project->versions()->with(['provinsi', 'kota', 'kecamatan', 'kelurahan'])->where('id',$versionId)->firstOrFail();
        $user = auth()->user();

        if ($user->role === 'issuer' && $project->issuer_id !== $user->id) {
            return response()->json(['message'=>'Unauthorized'],403);
        }

        return response()->json(['version'=>$version]);
    }

    public function destroy($id)
    {
        $project = Project::with('activeVersion')->findOrFail($id);

        if ($project->issuer_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized. Anda bukan pemilik proyek ini.'], 403);
        }

        $status = $project->activeVersion->status ?? 'draft';
        $versionNumber = $project->activeVersion->version_number ?? 1;

        if ($status !== 'draft' || $versionNumber > 1) {
            return response()->json([
                'message' => 'Akses ditolak. Hanya proyek Draft awal (Versi 1) yang belum pernah diajukan yang dapat dihapus.'
            ], 400);
        }

        try {
            $project->delete();
            return response()->json(['message' => 'Project deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Gagal menghapus proyek: ' . $e->getMessage()], 500);
        }
    }

    public function adminList()
    {
        $projects = Project::with([
            'issuer', 
            'activeVersion.documents', 
            'activeVersion.auditReport.auditor',
            'activeVersion.provinsi',
            'activeVersion.kota',
            'activeVersion.kecamatan', 
            'activeVersion.kelurahan', 
            'versions' => function($query) {
                $query->orderBy('version_number', 'desc')->with('documents');
            }
        ])
        ->whereHas('activeVersion', function ($q) {
            $q->where('status', '!=', 'draft'); 
        })
        ->latest()
        ->get();

        return response()->json($projects);
    }

    public function adminApprove(Request $request, $projectId)
    {
        $project = Project::with('activeVersion')->findOrFail($projectId);
        $version = $project->activeVersion;

        if ($version->status !== 'submitted') {
            return response()->json(['message'=>'Version not ready for admin approval'],400);
        }

        DB::beginTransaction();
        try {
            $version->update([
                'admin_verification_status'=>'approved',
                'status'=>'admin_approved'
            ]);

            $dataHash = $this->saveSnapshot($project, $version, 'admin_approved');

            if ($request->has('tx_hash')) {
                $project->update(['tx_hash' => $request->tx_hash]);
            }

            DB::commit();
            return response()->json([
                'message'=>'Admin approved',
                'dataHash' => $dataHash 
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    public function adminReject(Request $request, $projectId)
    {
        $request->validate(['note'=>'required|string']);

        $project = Project::with('activeVersion')->findOrFail($projectId);
        $version = $project->activeVersion;

        if ($version->status !== 'submitted') {
            return response()->json(['message'=>'Version not waiting admin review'],400);
        }

        DB::beginTransaction();
        try {
            $version->update([
                'status'=>'rejected',
                'admin_verification_status'=>'rejected',
                'admin_notes'=>$request->note,
                'is_locked'=>false
            ]);

            // 👉 FIX: Status diubah menjadi murni 'rejected' agar cocok dengan pencarian URI di React
            $dataHash = $this->saveSnapshot($project, $version, 'admin_rejected');

            if ($request->has('tx_hash')) {
                $project->update(['tx_hash' => $request->tx_hash]);
            }

            DB::commit();
            return response()->json([
                'message'=>'Rejected by admin',
                'dataHash' => $dataHash
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    public function adminListProject(Request $request, $id)
    {
        try {
            $project = Project::findOrFail($id);
            $version = $project->activeVersion;

            if ($version->auditor_verification_status !== 'approved') {
                return response()->json(['message'=>'Version not verified by auditor'],403);
            }

            if ($version->status === 'listed') {
                return response()->json(['message'=>'Already listed'], 400);
            }

            DB::beginTransaction();
            
            $version->update(['status'=>'listed']);

            $dataHash = $this->saveSnapshot($project, $version, 'listed');

            if ($request->has('tx_hash')) {
                $project->update(['tx_hash' => $request->tx_hash]);
            }

            DB::commit();

            return response()->json([
                'message'=>'Project version officially listed and NFT minted',
                'version'=>$version,
                'tx_hash'=>$project->tx_hash,
                'dataHash'=>$dataHash 
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error Laravel: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function adminListingQueue()
    {
        $projects = Project::with([
                'activeVersion.provinsi', 
                'activeVersion.kota',
                'activeVersion.kecamatan', 
                'activeVersion.kelurahan'
            ])
            ->whereHas('activeVersion', function ($q) {
                $q->where('auditor_verification_status','approved')
                ->where('status','auditor_verified');
            })
            ->latest()
            ->get();

        return response()->json($projects);
    }

    public function auditorList()
    {
        $projects = Project::with([
            'issuer', 
            'activeVersion.documents', 
            'activeVersion.auditReport.auditor',
            'activeVersion.provinsi',
            'activeVersion.kota',
            'activeVersion.kecamatan', 
            'activeVersion.kelurahan', 
            'versions' => function($query) {
                $query->orderBy('version_number', 'desc')->with('documents');
            }
        ])
        ->whereHas('activeVersion', function ($q) {
            $q->where('admin_verification_status', 'approved');
        })
        ->latest()
        ->get();

        return response()->json($projects);
    }
    
    public function auditorVerify(Request $request, $projectId)
    {
        $request->validate([
            'calculation_method' => 'required|in:system_estimated,actual_inverter',
            'verification_checklist' => 'required|array', 
            'baseline_emission_factor' => 'required|numeric|min:0',
            'onsite_measurement_date' => 'nullable|date', 
            'audit_notes' => 'nullable|string',
            'audit_documents.*' => 'required|mimes:pdf|max:10240',
            'audit_images.*' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'verified_generation_kwh' => 'required_if:calculation_method,actual_inverter|nullable|numeric|min:0',
        ]);

        $project = Project::with('activeVersion')->findOrFail($projectId);
        $version = $project->activeVersion;

        if ($version->status !== 'admin_approved') {
            return response()->json(['message' => 'Proyek belum siap atau belum disetujui oleh Admin untuk diaudit.'], 400);
        }

        $capacity = $version->total_system_capacity_kwp;
        $finalGenerationKwh = 0;

        if ($request->calculation_method === 'system_estimated') {
            $startDate = \Carbon\Carbon::parse($version->period_start);
            $endDate = \Carbon\Carbon::parse($version->period_end);
            $performanceRatio = 0.75; 

            $pshData = DB::table('psh_averages') 
                         ->where('kode_provinsi', $version->kode_provinsi)
                         ->first();

            if (!$pshData) {
                return response()->json(['message' => 'Gagal hitung otomatis. Data PSH rata-rata untuk kode provinsi ' . $version->kode_provinsi . ' tidak ditemukan.'], 422);
            }

            $totalPshAccumulated = 0;
            $currentDate = $startDate->copy();

            while ($currentDate->lte($endDate)) {
                $monthColumn = strtolower($currentDate->format('M')); 
                $dailyPsh = $pshData->$monthColumn ?? 0;
                $totalPshAccumulated += $dailyPsh;
                $currentDate->addDay();
            }

            $finalGenerationKwh = $capacity * $totalPshAccumulated * $performanceRatio;
        } else {
            $finalGenerationKwh = $request->verified_generation_kwh;
        }

        $calculatedCarbonReduction = ($finalGenerationKwh / 1000) * $request->baseline_emission_factor;

        $hasOverlap = AuditReport::whereHas('projectVersion', function ($query) use ($projectId, $version) {
            $query->where('project_id', $projectId)
                  ->where(function ($q) use ($version) {
                      $q->whereBetween('period_start', [$version->period_start, $version->period_end])
                        ->orWhereBetween('period_end', [$version->period_start, $version->period_end]);
                  });
        })->exists();

        if ($hasOverlap) {
            return response()->json(['message' => 'Peringatan Sistem: Periode klaim tumpang tindih dengan data sertifikat audit yang sudah diterbitkan sebelumnya.'], 422); 
        }

        DB::beginTransaction();
        try {
            AuditReport::create([
                'project_version_id' => $version->id,
                'auditor_id' => auth()->id(),
                'calculation_method' => $request->calculation_method, 
                'verification_checklist' => $request->verification_checklist, 
                'verified_installed_capacity_kwp' => $capacity, 
                'verified_generation_kwh' => $finalGenerationKwh, 
                'baseline_emission_factor' => $request->baseline_emission_factor,
                'carbon_reduction_amount_ton' => $calculatedCarbonReduction, 
                'onsite_measurement_date' => $request->onsite_measurement_date,
                'audit_notes' => $request->audit_notes,
            ]);

            if ($request->hasFile('audit_documents')) {
                foreach ($request->file('audit_documents') as $file) {
                    $path = $file->store('projects/audit_reports', 'public');
                    ProjectDocument::create([
                        'project_version_id' => $version->id,
                        'type' => 'document',
                        'original_filename' => $file->getClientOriginalName(),
                        'file_path' => $path,
                        'uploader_role' => 'auditor'
                    ]);
                }
            }

            if ($request->hasFile('audit_images')) {
                foreach ($request->file('audit_images') as $file) {
                    $path = $file->store('projects/audit_images', 'public');
                    ProjectDocument::create([
                        'project_version_id' => $version->id,
                        'type' => 'image',
                        'original_filename' => $file->getClientOriginalName(),
                        'file_path' => $path,
                        'uploader_role' => 'auditor'
                    ]);
                }
            }

            $version->update([
                'auditor_verification_status' => 'approved',
                'status' => 'auditor_verified'
            ]);

            $dataHash = $this->saveSnapshot($project, $version, 'auditor_verified');

            DB::commit();
            
            return response()->json([
                'message' => 'Laporan Berita Acara Audit Berhasil Disimpan. Status Proyek: Auditor Verified.',
                'calculated_reduction' => $calculatedCarbonReduction,
                'dataHash' => $dataHash
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Gagal memproses validasi data audit: ' . $e->getMessage()], 500);
        }
    }

    public function auditorReject(Request $request, $projectId)
    {
        $request->validate(['note'=>'required|string']);

        $project = Project::with('activeVersion')->findOrFail($projectId);
        $version = $project->activeVersion;

        if ($version->status !== 'admin_approved') {
            return response()->json(['message'=>'Version not waiting auditor review'],400);
        }

        DB::beginTransaction();
        try {
            $version->update([
                'status'=>'rejected',
                'auditor_verification_status'=>'rejected',
                'auditor_notes'=>$request->note,
                'is_locked'=>false
            ]);

            // 👉 FIX: Status diubah menjadi murni 'rejected' agar cocok dengan pencarian URI di React
            $dataHash = $this->saveSnapshot($project, $version, 'auditor_rejected');

            if ($request->has('tx_hash')) {
                $project->update(['tx_hash' => $request->tx_hash]);
            }

            DB::commit();
            return response()->json([
                'message'=>'Rejected by auditor',
                'dataHash' => $dataHash
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getSnapshot($projectId, $versionId, $status)
    {
        $snapshot = ProjectSnapshot::where('project_id', $projectId)
            ->where('project_version_id', $versionId)
            ->where('status_at_snapshot', $status)
            ->first();

        if (!$snapshot) {
            return response()->json(['error' => 'Snapshot tidak ditemukan untuk status ini'], 404);
        }

        return response()->json([
            'metadata' => $snapshot->snapshot_data['metadata'],
            'hash_info' => [
                'status' => $snapshot->status_at_snapshot,
                'recorded_at' => $snapshot->created_at,
                'expected_blockchain_hash' => $snapshot->data_hash
            ]
        ], 200, [], JSON_UNESCAPED_SLASHES);
    }

    public function saveTxHash(Request $request, $id)
    {
        $request->validate([
            'tx_hash' => 'required|string'
        ]);

        $project = Project::findOrFail($id);
        
        $project->update([
            'tx_hash' => $request->tx_hash
        ]);

        return response()->json([
            'message' => 'Transaction hash successfully synchronized to database',
            'tx_hash' => $request->tx_hash
        ]);
    }

    // ==========================================
    // 👉 NEW: FUNGSI REVERT STATUS (FAIL-SAFE)
    // ==========================================
    public function revertStatus(Request $request, $id)
    {
        $request->validate([
            'previous_status' => 'required|string',
        ]);

        $project = Project::with('activeVersion')->findOrFail($id);
        $version = $project->activeVersion;

        DB::beginTransaction();
        try {
            // 1. Kembalikan status version
            $version->update([
                'status' => $request->previous_status,
                'admin_verification_status' => $request->previous_status === 'submitted' ? 'pending' : $version->admin_verification_status,
                'auditor_verification_status' => $request->previous_status === 'admin_approved' ? 'pending' : $version->auditor_verification_status,
                'is_locked' => in_array($request->previous_status, ['submitted', 'admin_approved', 'auditor_verified']) ? true : false
            ]);

            // 2. Hapus snapshot terbaru jika statusnya tidak sama dengan previous_status (rollback)
            $latestSnapshot = ProjectSnapshot::where('project_version_id', $version->id)->latest()->first();
            if ($latestSnapshot && $latestSnapshot->status_at_snapshot !== $request->previous_status) {
                $latestSnapshot->delete();
            }

            // 3. Jika proyek belum punya NFT di awal, kita bisa pastikan tx_hash dihapus
            // Tapi karena bisa jadi ini dari proses revisi yang sudah punya tx_hash,
            // lebih aman kita tidak menyentuh tx_hash yang sudah legitimate (misal dari submitted).
            // Aturan ini bisa disesuaikan jika ingin mengosongkan tx_hash proyek yang baru dibuat.
            
            DB::commit();
            return response()->json(['message' => 'Status berhasil dikembalikan ke ' . $request->previous_status]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Gagal revert status: ' . $e->getMessage()], 500);
        }
    }
}