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
            // Validasi data teknis baru
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
                // Data teknis baru
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

            // HANDLE UPLOAD (Reusable Logic)
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
        // 1. Validasi Input
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'location_country' => 'sometimes|required|string',
            'location_province' => 'sometimes|required|string',
            'location_city' => 'sometimes|required|string',
            'address' => 'sometimes|required|string',
            'project_type' => 'nullable|string',
            'description' => 'nullable|string',
            'project_images.*' => 'nullable|image|mimes:jpeg,png,jpg|max:5120', // Foto max 5MB
            'project_documents.*' => 'nullable|mimes:pdf,doc,docx|max:10240',   // Dokumen max 10MB
            // Validasi data teknis baru
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

        // 2. Pastikan yang mengedit adalah pemiliknya dan statusnya masih Draft
        if ($project->issuer_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!in_array($version->status, ['draft', 'revision'])) {
            return response()->json(['message' => 'Hanya proyek berstatus Draft yang dapat diedit.'], 400);
        }

        DB::beginTransaction();
        try {
            // 3. Update data teks (termasuk spesifikasi teknis)
            $version->update($request->only([
                'name', 'description', 'location_country', 
                'location_province', 'location_city', 'address', 'project_type',
                'panel_capacity_wp', 'inverter_capacity_kw', 'area_size_m2',
                'number_of_panels', 'installation_date', 'installation_type',
                'panel_brand', 'inverter_brand'
            ]));

            // 4. Tambahkan file baru jika ada yang diupload (File lama tidak dihapus)
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
        // Ambil project beserta active_version dan dokumen-dokumennya
        $project = Project::with('activeVersion.documents')->findOrFail($id);
        $oldVersion = $project->activeVersion;

        // Pastikan hanya bisa revisi jika statusnya rejected
        if ($oldVersion->status !== 'rejected') {
            return response()->json(['message' => 'Hanya proyek yang ditolak (rejected) yang dapat direvisi.'], 400);
        }

        DB::beginTransaction();
        try {
            // 1. Buat Versi Baru (Copy dari versi lama, tapi status jadi Draft)
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
                'status' => 'draft', // Kembali ke draft
                // Salin data teknis dari versi sebelumnya
                'panel_capacity_wp' => $oldVersion->panel_capacity_wp,
                'inverter_capacity_kw' => $oldVersion->inverter_capacity_kw,
                'area_size_m2' => $oldVersion->area_size_m2,
                'number_of_panels' => $oldVersion->number_of_panels,
                'installation_date' => $oldVersion->installation_date,
                'installation_type' => $oldVersion->installation_type,
                'panel_brand' => $oldVersion->panel_brand,
                'inverter_brand' => $oldVersion->inverter_brand,
            ]);

            // 2. CARRY OVER DOKUMEN (Salin pointer dokumen lama ke versi baru)
            if ($oldVersion->documents) {
                foreach ($oldVersion->documents as $doc) {
                    ProjectDocument::create([
                        'project_version_id' => $newVersion->id,
                        'type' => $doc->type,
                        'original_filename' => $doc->original_filename,
                        'file_path' => $doc->file_path, // Menggunakan file fisik yang sama
                        'uploader_role' => $doc->uploader_role
                    ]);
                }
            }

            // 3. Update pointer versi aktif di tabel induk
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
        // Handle Images (Foto Galeri)
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

        // Handle Documents (PDF/Legal)
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
            // 'admin_verification_status'=>'pending',
            // 'auditor_verification_status'=>'pending',
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
        $projects = Project::with([
            'issuer', // Menarik data User (Issuer)
            'activeVersion.documents', 
            'activeVersion.auditReport.auditor' // Menarik data User (Auditor) dari tabel laporan
        ])
        ->where('issuer_id', auth()->id())
        ->latest()
        ->get();

        return response()->json($projects);
    }

    //ISSUER SHOW Project By Id
    public function show($id)
    {
        $projects = Project::with([
            'issuer', // Menarik data User (Issuer)
            'activeVersion.documents', 
            'activeVersion.auditReport.auditor' // Menarik data User (Auditor) dari tabel laporan
        ])
        ->findOrFail($id);

        $user = auth()->user();

        // ISSUER hanya boleh lihat project miliknya
        if ($user->role === 'issuer' &&
            $project->issuer_id !== $user->id) {

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

        // issuer hanya boleh akses project sendiri
        if ($user->role === 'issuer' &&
            $project->issuer_id !== $user->id) {

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

        if ($user->role === 'issuer' &&
            $project->issuer_id !== $user->id) {

            return response()->json([
                'message'=>'Unauthorized'
            ],403);
        }

        return response()->json([
            'version'=>$version
        ]);
    }

    // ===============================
    // ADMIN LIST PROJECTS
    // ===============================
    public function adminList()
    {
        // 1. Tambahkan 'issuer' ke dalam with() agar frontend bisa membaca nama perusahaan/issuer
        // 2. Ubah query agar admin bisa melihat SEMUA proyek, kecuali mungkin yang masih 'draft' murni di sisi issuer
        $projects = Project::with([
            'issuer', // Menarik data User (Issuer)
            'activeVersion.documents', 
            'activeVersion.auditReport.auditor' // Menarik data User (Auditor) dari tabel laporan
        ])
            ->whereHas('activeVersion', function ($q) {
                // Admin hanya melihat proyek yang sudah pernah disubmit minimal 1 kali
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
    // ===============================
    // ADMIN FINAL LISTING
    // ===============================
    public function adminListProject($id)
    {
        $project = Project::findOrFail($id);
        $version = $project->activeVersion;

        if ($version->auditor_verification_status !== 'approved') {
            return response()->json([
                'message'=>'Version not verified by auditor'
            ],403);
        }

        if ($version->status === 'listed') {
            return response()->json([
                'message'=>'Already listed'
            ]);
        }

        $version->update([
            'status'=>'listed'
        ]);

        return response()->json([
            'message'=>'Project version officially listed',
            'version'=>$version
        ]);
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
    public function auditorList()
    {
        // Ubah dari with('activeVersion') menjadi with(['activeVersion', 'issuer'])
        $projects = Project::with([
            'issuer', // Menarik data User (Issuer)
            'activeVersion.documents', 
            'activeVersion.auditReport.auditor' // Menarik data User (Auditor) dari tabel laporan
        ])
            ->whereHas('activeVersion', function ($q) {
                // Di sini kamu bisa menyesuaikan status apa saja yang boleh ditarik auditor
                // Sesuai kode aslimu:
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
        // 1. Validasi Input Teknis & File
        $request->validate([
            'verified_installed_capacity_kwp' => 'required|numeric',
            'verified_annual_generation_kwh' => 'required|numeric',
            'baseline_emission_factor' => 'required|numeric',
            'expected_carbon_reduction_ton_per_year' => 'required|numeric',
            'onsite_measurement_date' => 'required|date',
            'audit_notes' => 'nullable|string',
            'audit_documents.*' => 'required|mimes:pdf|max:10240', // File PDF max 10MB
            'audit_images.*' => 'nullable|image|mimes:jpeg,png,jpg|max:5120', // Foto max 5MB
        ]);

        $project = Project::with('activeVersion')->findOrFail($projectId);
        $version = $project->activeVersion;

        if ($version->status !== 'admin_approved') {
            return response()->json(['message' => 'Proyek belum siap untuk diaudit'], 400);
        }

        DB::beginTransaction();
        try {
            // 2. Simpan Data ke Tabel audit_reports
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

            // 3. Handle Upload File Laporan (PDF)
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

            // 4. Handle Upload Foto Bukti Lapangan (Images)
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

            // 5. Update Status Proyek
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