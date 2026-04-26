<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\ProjectDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\ProjectVersion;
use App\Models\AuditReport;

class ProjectController extends Controller
{

    // ===============================
    // ISSUER CREATE PROJECT
    // ===============================
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'location_country' => 'required',
            'location_province' => 'required',
            'location_city' => 'required',
            'address' => 'required',
            'project_images.*' => 'nullable|image|max:5120',
            'project_documents.*' => 'nullable|mimes:pdf|max:10240',
            'panel_capacity_wp' => 'nullable|numeric',
            'inverter_capacity_kw' => 'nullable|numeric',
            'area_size_m2' => 'nullable|numeric',
            'number_of_panels' => 'nullable|integer',
            'installation_date' => 'nullable|date',
            'installation_type' => 'nullable|string',
            'panel_brand' => 'nullable|string',
            'inverter_brand' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $project = Project::create(['issuer_id' => Auth::id()]);

            $version = ProjectVersion::create([
                'project_id' => $project->id,
                'version_number' => 1,
                'name' => $request->name,
                'description' => $request->description,
                'location_country' => $request->location_country,
                'location_province' => $request->location_province,
                'location_city' => $request->location_city,
                'address' => $request->address,
                'status' => 'draft',
                'panel_capacity_wp' => $request->panel_capacity_wp,
                'inverter_capacity_kw' => $request->inverter_capacity_kw,
                'area_size_m2' => $request->area_size_m2,
                'number_of_panels' => $request->number_of_panels,
                'installation_date' => $request->installation_date,
                'installation_type' => $request->installation_type,
                'panel_brand' => $request->panel_brand,
                'inverter_brand' => $request->inverter_brand,
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

    // ==========================================
    // ISSUER: UPDATE DRAFT PROJECT
    // ==========================================
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'location_country' => 'sometimes|required|string',
            'location_province' => 'sometimes|required|string',
            'location_city' => 'sometimes|required|string',
            'address' => 'sometimes|required|string',
            'project_type' => 'nullable|string',
            'description' => 'nullable|string',
            'project_images.*' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'project_documents.*' => 'nullable|mimes:pdf,doc,docx|max:10240',
            'panel_capacity_wp' => 'nullable|numeric',
            'inverter_capacity_kw' => 'nullable|numeric',
            'area_size_m2' => 'nullable|numeric',
            'number_of_panels' => 'nullable|integer',
            'installation_date' => 'nullable|date',
            'installation_type' => 'nullable|string',
            'panel_brand' => 'nullable|string',
            'inverter_brand' => 'nullable|string',
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
                'name', 'description', 'location_country', 
                'location_province', 'location_city', 'address', 'project_type',
                'panel_capacity_wp', 'inverter_capacity_kw', 'area_size_m2',
                'number_of_panels', 'installation_date', 'installation_type',
                'panel_brand', 'inverter_brand'
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

    // ==========================================
    // ISSUER: REVISE REJECTED PROJECT
    // ==========================================
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
                'location_country' => $oldVersion->location_country,
                'location_province' => $oldVersion->location_province,
                'location_city' => $oldVersion->location_city,
                'address' => $oldVersion->address,
                'project_type' => $oldVersion->project_type,
                'status' => 'draft',
                'panel_capacity_wp' => $oldVersion->panel_capacity_wp,
                'inverter_capacity_kw' => $oldVersion->inverter_capacity_kw,
                'area_size_m2' => $oldVersion->area_size_m2,
                'number_of_panels' => $oldVersion->number_of_panels,
                'installation_date' => $oldVersion->installation_date,
                'installation_type' => $oldVersion->installation_type,
                'panel_brand' => $oldVersion->panel_brand,
                'inverter_brand' => $oldVersion->inverter_brand,
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

    // ==========================================
    // HELPER: UPLOAD PROJECT FILES
    // ==========================================
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

    //ISSUER SUBMIT PROJECT
    public function submit($id)
    {
        $project = Project::findOrFail($id);
        $version = $project->activeVersion;

        if ($project->issuer_id !== auth()->id()) {
            return response()->json(['message'=>'Unauthorized'],403);
        }

        if ($version->status !== 'draft') {
            return response()->json([
                'message'=>'Already submitted'
            ],400);
        }

        $version->update([
            'status'=>'submitted',
            'is_locked'=>true
        ]);

        return response()->json([
            'message'=>'Version submitted',
            'version'=>$version
        ]);
    }

    //Issuer Show All Project
    public function issuerProjects()
    {
        // 👇 PERBAIKAN: Menambahkan relasi 'versions' agar timeline muncul 👇
        $projects = Project::with([
            'issuer',
            'activeVersion.documents', 
            'activeVersion.auditReport.auditor',
            'versions' => function($query) {
                $query->orderBy('version_number', 'desc'); // Urutkan dari versi terbaru ke terlama
            }
        ])
        ->where('issuer_id', auth()->id())
        ->latest()
        ->get();

        return response()->json($projects);
    }

    //ISSUER SHOW Project By Id
    public function show($id)
    {
        // 👇 PERBAIKAN TYPO: Mengubah $projects menjadi $project 👇
        $project = Project::with([
            'issuer', 
            'activeVersion.documents', 
            'activeVersion.auditReport.auditor'
        ])
        ->findOrFail($id);

        $user = auth()->user();

        if ($user->role === 'issuer' && $project->issuer_id !== $user->id) {
            return response()->json([
                'message'=>'Unauthorized'
            ],403);
        }

        return response()->json([
            'project'=>$project,
            'active_version'=>$project->activeVersion
        ]);
    }

    //ISSUER SHOW ALL VERSIONS
    public function versions($id)
    {
        $project = Project::with('versions')
            ->findOrFail($id);

        $user = auth()->user();

        if ($user->role === 'issuer' && $project->issuer_id !== $user->id) {
            return response()->json([
                'message'=>'Unauthorized'
            ],403);
        }

        return response()->json([
            'project_id'=>$project->id,
            'versions'=>$project->versions
                ->sortByDesc('version_number')
                ->values()
        ]);
    }

    //ISSUER SHOW SPECIFIC VERSION
    public function showVersion($projectId, $versionId)
    {
        $project = Project::findOrFail($projectId);

        $version = $project->versions()
            ->where('id',$versionId)
            ->firstOrFail();

        $user = auth()->user();

        if ($user->role === 'issuer' && $project->issuer_id !== $user->id) {
            return response()->json([
                'message'=>'Unauthorized'
            ],403);
        }

        return response()->json([
            'version'=>$version
        ]);
    }

    // ==========================================
    // ISSUER: DELETE PROJECT
    // ==========================================
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

    // ===============================
    // ADMIN LIST PROJECTS
    // ===============================
    public function adminList()
    {
        $projects = Project::with([
            'issuer', 
            'activeVersion.documents', 
            'activeVersion.auditReport.auditor',
            // 👇 TAMBAHKAN with('documents') DI SINI 👇
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

    // ===============================
    // ADMIN APPROVE
    // ===============================
    public function adminApprove($projectId)
    {
        $project = Project::with('activeVersion')->findOrFail($projectId);

        $version = $project->activeVersion;

        if ($version->status !== 'submitted') {
            return response()->json([
                'message'=>'Version not ready for admin approval'
            ],400);
        }

        $version->update([
            'admin_verification_status'=>'approved',
            'status'=>'admin_approved'
        ]);

        return response()->json([
            'message'=>'Admin approved'
        ]);
    }
    
    // ===============================
    // ADMIN REJECT
    // ===============================
    public function adminReject(Request $request,$projectId)
    {
        $request->validate([
            'note'=>'required|string'
        ]);

        $project = Project::with('activeVersion')->findOrFail($projectId);
        $version = $project->activeVersion;

        if ($version->status !== 'submitted') {
            return response()->json([
                'message'=>'Version not waiting admin review'
            ],400);
        }

        $version->update([
            'status'=>'rejected',
            'admin_verification_status'=>'rejected',
            'admin_notes'=>$request->note,
            'is_locked'=>false
        ]);

        return response()->json([
            'message'=>'Rejected by admin'
        ]);
    }
    
    //ADMIN LIST PROJECT (FINAL APPROVAL + MINT NFT)
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

            // Update status
            $version->update(['status'=>'listed']);

            // Simpan tx_hash
            if ($request->has('tx_hash')) {
                $project->update(['tx_hash' => $request->tx_hash]);
            }

            return response()->json([
                'message'=>'Project version officially listed and NFT minted',
                'version'=>$version,
                'tx_hash'=>$project->tx_hash
            ]);

        } catch (\Exception $e) {
            // 👇 INI KUNCINYA: Menangkap error asli dari Laravel 👇
            return response()->json([
                'message' => 'Error Laravel: ' . $e->getMessage()
            ], 500);
        }
    }
    
    // ===============================
    // ADMIN LISTING QUEUE
    // ===============================
    public function adminListingQueue()
    {
        $projects = Project::with('activeVersion')
            ->whereHas('activeVersion', function ($q) {
                $q->where('auditor_verification_status','approved')
                ->where('status','auditor_verified');
            })
            ->latest()
            ->get();

        return response()->json($projects);
    }

    // ===============================
    // AUDITOR LIST PROJECTS
    // ===============================
    // LAKUKAN HAL YANG SAMA DI FUNGSI auditorList()
    public function auditorList()
    {
        $projects = Project::with([
            'issuer', 
            'activeVersion.documents', 
            'activeVersion.auditReport.auditor',
            // 👇 TAMBAHKAN with('documents') DI SINI 👇
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
    
    // ===============================
    // AUDITOR VERIFY
    // ===============================
    public function auditorVerify(Request $request, $projectId)
    {
        $request->validate([
            'verified_installed_capacity_kwp' => 'required|numeric',
            'verified_annual_generation_kwh' => 'required|numeric',
            'baseline_emission_factor' => 'required|numeric',
            'expected_carbon_reduction_ton_per_year' => 'required|numeric',
            'onsite_measurement_date' => 'required|date',
            'audit_notes' => 'nullable|string',
            'audit_documents.*' => 'required|mimes:pdf|max:10240',
            'audit_images.*' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        $project = Project::with('activeVersion')->findOrFail($projectId);
        $version = $project->activeVersion;

        if ($version->status !== 'admin_approved') {
            return response()->json(['message' => 'Proyek belum siap untuk diaudit'], 400);
        }

        DB::beginTransaction();
        try {
            AuditReport::create([
                'project_version_id' => $version->id,
                'auditor_id' => auth()->id(),
                'verified_installed_capacity_kwp' => $request->verified_installed_capacity_kwp,
                'verified_annual_generation_kwh' => $request->verified_annual_generation_kwh,
                'baseline_emission_factor' => $request->baseline_emission_factor,
                'expected_carbon_reduction_ton_per_year' => $request->expected_carbon_reduction_ton_per_year,
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

            DB::commit();
            return response()->json(['message' => 'Laporan Audit berhasil disimpan dan proyek diverifikasi']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ===============================
    // AUDITOR REJECT
    // ===============================
    public function auditorReject(Request $request,$projectId)
    {
        $request->validate([
            'note'=>'required|string'
        ]);

        $project = Project::with('activeVersion')->findOrFail($projectId);
        $version = $project->activeVersion;

        if ($version->status !== 'admin_approved') {
            return response()->json([
                'message'=>'Version not waiting auditor review'
            ],400);
        }

        $version->update([
            'status'=>'rejected',
            'auditor_verification_status'=>'rejected',
            'auditor_notes'=>$request->note,
            'is_locked'=>false
        ]);

        return response()->json([
            'message'=>'Rejected by auditor'
        ]);
    }
}