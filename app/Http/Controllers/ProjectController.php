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
use App\Helpers\EthereumSigner;

class ProjectController extends Controller
{
    // ==========================================
    // HELPER: GENERATE METADATA KONSISTEN
    // ==========================================
    
    /**
     * Membentuk struktur metadata proyek untuk kebutuhan snapshot atau blockchain.
     * Menggabungkan data proyek, lokasi, kapasitas, dan catatan audit/admin.
     */
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

        if (!empty($version->admin_notes)) {
            $attributes[] = ["trait_type" => "Admin Revision Notes", "value" => $version->admin_notes];
        }
        
        if (!empty($version->auditor_notes)) {
            $attributes[] = ["trait_type" => "Auditor Revision Notes", "value" => $version->auditor_notes];
        }

        if (in_array($version->status, ['auditor_verified', 'listed', 'returned_to_auditor']) && $version->auditReport) {
            $audit = $version->auditReport;
            $attributes[] = ["trait_type" => "Auditor", "value" => $audit->auditor->name ?? "Auditor"];
            
            $methodName = $audit->calculation_method === 'system_estimated' ? 'Conservative System Estimation' : 'Actual Inverter Data';
            $attributes[] = ["trait_type" => "Calculation Method", "value" => $methodName];
            $attributes[] = ["trait_type" => "Verified Capacity (kWp)", "value" => (string)$audit->verified_installed_capacity_kwp];
            $attributes[] = ["trait_type" => "Verified Generation (kWh)", "value" => (string)$audit->verified_generation_kwh];
            $attributes[] = ["trait_type" => "Carbon Reduction (Ton)", "value" => (string)$audit->carbon_reduction_amount_ton];
            
            if (!empty($audit->audit_notes)) {
                $attributes[] = ["trait_type" => "Auditor Report Notes", "value" => $audit->audit_notes];
            }
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
                    $images[] = ["name" => $doc->original_filename, "url" => $url, "role" => $doc->uploader_role];
                } else {
                    $documents[] = ["name" => $doc->original_filename, "url" => $url, "role" => $doc->uploader_role];
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

    /**
     * Helper untuk menyimpan snapshot data proyek beserta hash-nya.
     * Biasanya dipanggil saat terjadi perubahan status penting.
     */
    private function saveSnapshot($project, $version, $status)
    {
        $project->loadMissing(['issuer']);
        $version->loadMissing(['documents', 'auditReport.auditor', 'provinsi', 'kota']);

        $metadataArray = $this->generateMetadataArray($project, $version);
        $jsonString = json_encode(["metadata" => $metadataArray], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $dataHash = hash('sha256', $jsonString);

        $snapshot = ProjectSnapshot::create([
            'project_id' => $project->id,
            'project_version_id' => $version->id,
            'status_at_snapshot' => $status,
            'snapshot_data' => ["metadata" => $metadataArray],
            'data_hash' => $dataHash
        ]);

        return [
            'dataHash' => $dataHash,
            'snapshotUri' => url("/api/snapshots/{$snapshot->id}"),
            'snapshotId' => $snapshot->id // 👉 ID GENERATED HERE
        ];
    }

    /**
     * HELPER: Meng-generate Digital Signature sebelum Frontend memanggil MetaMask
     */
    public function requestMintSignature($projectId)
    {
        try {
            $project = Project::with('activeVersion.auditReport', 'issuer')->findOrFail($projectId);
            $version = $project->activeVersion;

            if ($version->status !== 'auditor_verified') {
                return response()->json(['error' => 'Proyek belum siap dicetak. Status harus auditor_verified.'], 400);
            }

            $auditReport = $version->auditReport;
            $issuerWallet = $project->issuer->wallet_address;

            if (!$issuerWallet) {
                return response()->json(['error' => 'Issuer belum memiliki wallet address.'], 400);
            }

            // 👉 KUNCI PERBAIKAN: Gunakan Helper toWei kita yang baru!
            $carbonAmount = $auditReport->carbon_reduction_amount_ton;
            $amountInWei = EthereumSigner::toWei($carbonAmount);

            // Generate Signature
            $signature = EthereumSigner::signMintData($issuerWallet, $project->id, $amountInWei);

            return response()->json([
                'message' => 'Signature generated successfully',
                'projectId' => $project->id,
                'projectName' => $version->name,
                'issuerWallet' => $issuerWallet,
                'amountInWei' => $amountInWei,
                'signature' => $signature
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Gagal membuat signature: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Menampilkan daftar proyek yang sudah diverifikasi dan rilis (listed) di market.
     */
    public function getMarketProjects()
    {
        $projects = Project::with([
            'issuer', 'activeVersion.documents', 'activeVersion.auditReport.auditor',
            'activeVersion.provinsi', 'activeVersion.kota'
        ])
        ->whereHas('activeVersion', function ($q) {
            $q->where('status', 'listed');
        })->latest()->get();

        return response()->json($projects);
    }

    /**
     * Menyimpan proyek baru (versi 1) dari Issuer ke dalam database.
     */
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

    /**
     * Memperbarui detail proyek yang masih berstatus draft atau revisi.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255', 
            'kode_provinsi' => 'sometimes|required|string|max:2',
            'kode_kota' => 'sometimes|required|string|max:5', 
            'kode_kecamatan' => 'sometimes|required|string|max:8',
            'kode_kelurahan' => 'sometimes|required|string|max:13', 
            'address' => 'sometimes|required|string',
            'project_images.*' => 'nullable|image|max:5120', 
            'project_documents.*' => 'nullable|mimes:pdf,doc,docx|max:10240',
        ]);

        $project = Project::with('activeVersion')->findOrFail($id);
        $version = $project->activeVersion;

        if ($project->issuer_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        if (!in_array($version->status, ['draft', 'revision'])) {
            return response()->json(['message' => 'Hanya draft yang dapat diedit.'], 400);
        }

        DB::beginTransaction();
        try {
            $version->update($request->only([
                'name', 'description', 'kode_provinsi', 'kode_kota', 'kode_kecamatan', 'kode_kelurahan', 
                'address', 'project_type', 'total_system_capacity_kwp', 'inverter_capacity_kw', 'installation_date', 
                'panel_brand', 'inverter_brand', 'period_start', 'period_end'
            ]));
            
            $this->uploadProjectFiles($request, $version->id, 'issuer');
            DB::commit();
            
            return response()->json(['message' => 'Draft project updated successfully', 'version' => $version]);
        } catch (\Exception $e) { 
            DB::rollBack(); 
            return response()->json(['error' => $e->getMessage()], 500); 
        }
    }

    /**
     * Membuat versi proyek baru sebagai revisi setelah sebelumnya ditolak (rejected).
     */
    public function reviseProject($id)
    {
        $project = Project::with('activeVersion.documents')->findOrFail($id);
        $oldVersion = $project->activeVersion;

        if ($oldVersion->status !== 'rejected') {
            return response()->json(['message' => 'Hanya proyek ditolak yang dapat direvisi.'], 400);
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

            // Duplikasi dokumen dari versi lama ke versi revisi
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
            
            return response()->json(['message' => 'Revision version created.', 'new_version_id' => $newVersion->id]);
        } catch (\Exception $e) { 
            DB::rollBack(); 
            return response()->json(['error' => $e->getMessage()], 500); 
        }
    }

    /**
     * Helper untuk memproses file upload (gambar dan dokumen) pada proyek dari Issuer.
     */
    private function uploadProjectFiles(Request $request, $versionId, $role)
    {
        if ($request->hasFile('project_images')) {
            foreach ($request->file('project_images') as $file) {
                // 👉 FOLDER KHUSUS ISSUER IMAGE
                $path = $file->store('projects/issuer_images', 'public');
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
                // 👉 FOLDER KHUSUS ISSUER DOCUMENT
                $path = $file->store('projects/issuer_documents', 'public');
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

    /**
     * Mengunci proyek draft dan mengubah statusnya menjadi 'submitted' untuk direview Admin.
     */
    public function submit($id)
    {
        $project = Project::findOrFail($id);
        $version = $project->activeVersion;

        if ($project->issuer_id !== auth()->id()) return response()->json(['message'=>'Unauthorized'],403);
        if ($version->status !== 'draft') return response()->json(['message'=>'Already submitted'],400);
        if (!$version->period_start || !$version->period_end) return response()->json(['message' => 'Claim Period harus diisi.'], 400);

        $version->update(['status'=>'submitted', 'is_locked'=>true]);

        $snap = $this->saveSnapshot($project, $version, 'submitted');

        return response()->json([
            'message'=>'Version submitted',
            'version'=>$version,
            'dataHash'=>$snap['dataHash'],
            'snapshotUri'=>$snap['snapshotUri'],
            'snapshotId'=>$snap['snapshotId'] // 👉 FIX DITAMBAHKAN
        ]);
    }

    /**
     * Mengambil daftar seluruh proyek yang dimiliki oleh Issuer yang sedang login.
     */
    public function issuerProjects()
    {
        $projects = Project::with([
            'issuer', 'activeVersion.documents', 'activeVersion.auditReport.auditor',
            'activeVersion.provinsi', 'activeVersion.kota', 'activeVersion.kecamatan', 'activeVersion.kelurahan', 
            'snapshots', // PASTIKAN RELASI INI ADA
            'versions' => function($query) { 
                $query->orderBy('version_number', 'desc'); 
            }
        ])->where('issuer_id', auth()->id())->latest()->paginate(10);

        return response()->json($projects);
    }

    /**
     * Menampilkan detail lengkap sebuah proyek berdasarkan ID.
     */
    public function show($id)
    {
        $project = Project::with([
            'issuer', 'activeVersion.documents', 'activeVersion.auditReport.auditor',
            'activeVersion.provinsi', 'activeVersion.kota', 'activeVersion.kecamatan', 'activeVersion.kelurahan',
            'snapshots'
        ])->findOrFail($id);

        if (auth()->user()->role === 'issuer' && $project->issuer_id !== auth()->user()->id) {
            return response()->json(['message'=>'Unauthorized'],403);
        }
        
        return response()->json(['project'=>$project, 'active_version'=>$project->activeVersion]);
    }

    /**
     * Menampilkan histori semua versi yang ada pada suatu proyek.
     */
    public function versions($id)
    {
        $project = Project::with('versions')->findOrFail($id);
        
        if (auth()->user()->role === 'issuer' && $project->issuer_id !== auth()->id()) {
            return response()->json(['message'=>'Unauthorized'],403);
        }
        
        return response()->json([
            'project_id' => $project->id, 
            'versions' => $project->versions->sortByDesc('version_number')->values()
        ]);
    }

    /**
     * Menampilkan detail dari versi spesifik suatu proyek.
     */
    public function showVersion($projectId, $versionId)
    {
        $project = Project::findOrFail($projectId);
        $version = $project->versions()->with(['provinsi', 'kota', 'kecamatan', 'kelurahan'])->where('id',$versionId)->firstOrFail();
        
        if (auth()->user()->role === 'issuer' && $project->issuer_id !== auth()->id()) {
            return response()->json(['message'=>'Unauthorized'],403);
        }
        
        return response()->json(['version'=>$version]);
    }

    /**
     * Menghapus proyek beserta versinya (Hanya berlaku jika status masih draft).
     */
    public function destroy($id)
    {
        $project = Project::with('activeVersion')->findOrFail($id);
        
        if ($project->issuer_id !== auth()->id()) return response()->json(['message' => 'Unauthorized.'], 403);
        if (($project->activeVersion->status ?? 'draft') !== 'draft') return response()->json(['message' => 'Akses ditolak.'], 400);
        
        try { 
            $project->delete(); 
            return response()->json(['message' => 'Project deleted']); 
        } catch (\Exception $e) { 
            return response()->json(['error' => $e->getMessage()], 500); 
        }
    }

    /**
     * Mengambil seluruh data proyek non-draft untuk panel Admin.
     */
    public function adminList()
    {
        $projects = Project::with([
            'issuer', 'activeVersion.documents', 'activeVersion.auditReport.auditor',
            'activeVersion.provinsi', 'activeVersion.kota', 'activeVersion.kecamatan', 'activeVersion.kelurahan', 
            'snapshots', // PASTIKAN RELASI INI ADA
            'versions' => function($query) { 
                $query->orderBy('version_number', 'desc')->with('documents'); 
            }
        ])->whereHas('activeVersion', function ($q) { 
            $q->where('status', '!=', 'draft'); 
        })->latest()->paginate(10);

        return response()->json($projects);
    }

    /**
     * Aksi Admin menyetujui proyek dari Issuer agar diteruskan ke Auditor.
     */
    public function adminApprove(Request $request, $projectId)
    {
        $project = Project::with('activeVersion')->findOrFail($projectId);
        $version = $project->activeVersion;
        
        if ($version->status !== 'submitted') return response()->json(['message'=>'Not ready'],400);

        DB::beginTransaction();
        try {
            $version->update([
                'admin_verification_status' => 'approved', 
                'status' => 'admin_approved'
            ]);
            $snap = $this->saveSnapshot($project, $version, 'admin_approved');
            
            if ($request->has('tx_hash')) {
                $project->update(['tx_hash' => $request->tx_hash]);
            }
            DB::commit();

            return response()->json([
                'message'=>'Admin approved',
                'dataHash' => $snap['dataHash'],
                'snapshotUri' => $snap['snapshotUri'],
                'snapshotId' => $snap['snapshotId'] // 👉 FIX DITAMBAHKAN
            ]);
        } catch (\Exception $e) { 
            DB::rollBack(); 
            return response()->json(['error' => $e->getMessage()], 500); 
        }
    }
    
    /**
     * Aksi Admin menolak proyek dari Issuer (misal: data tidak valid).
     */
    public function adminReject(Request $request, $projectId)
    {
        $request->validate(['note'=>'required|string']);
        $project = Project::with('activeVersion')->findOrFail($projectId);
        $version = $project->activeVersion;
        
        if ($version->status !== 'submitted') return response()->json(['message'=>'Not waiting admin review'],400);

        DB::beginTransaction();
        try {
            $version->update([
                'status'=>'rejected', 
                'admin_verification_status'=>'rejected', 
                'admin_notes'=>$request->note, 
                'is_locked'=>false
            ]);
            $snap = $this->saveSnapshot($project, $version, 'admin_rejected');
            
            if ($request->has('tx_hash')) {
                $project->update(['tx_hash' => $request->tx_hash]);
            }
            DB::commit();

            return response()->json([
                'message'=>'Rejected by admin',
                'dataHash' => $snap['dataHash'],
                'snapshotUri' => $snap['snapshotUri'],
                'snapshotId' => $snap['snapshotId'] // 👉 FIX DITAMBAHKAN
            ]);
        } catch (\Exception $e) { 
            DB::rollBack(); 
            return response()->json(['error' => $e->getMessage()], 500); 
        }
    }

    /**
     * Admin merilis (listing) proyek ke marketplace setelah audit disetujui.
     */
    public function adminListProject(Request $request, $id)
    {
        try {
            $project = Project::findOrFail($id);
            $version = $project->activeVersion;
            
            if ($version->auditor_verification_status !== 'approved') return response()->json(['message'=>'Not verified'],403);

            DB::beginTransaction();
            $version->update(['status'=>'listed']);
            $snap = $this->saveSnapshot($project, $version, 'listed');
            
            if ($request->has('tx_hash')) {
                $project->update(['tx_hash' => $request->tx_hash]);
            }
            DB::commit();

            return response()->json([
                'message'=>'Project officially listed',
                'version'=>$version,
                'dataHash' => $snap['dataHash'],
                'snapshotUri' => $snap['snapshotUri'],
                'snapshotId' => $snap['snapshotId'] // 👉 FIX DITAMBAHKAN
            ]);
        } catch (\Exception $e) { 
            DB::rollBack(); 
            return response()->json(['message' => $e->getMessage()], 500); 
        }
    }

    /**
     * Admin mengembalikan laporan ke Auditor jika ada ketidaksesuaian hasil audit.
     */
    public function adminRejectAuditor(Request $request, $projectId)
    {
        $request->validate(['note' => 'required|string']);
        $project = Project::with('activeVersion')->findOrFail($projectId);
        $version = $project->activeVersion;
        
        if ($version->status !== 'auditor_verified') return response()->json(['message'=>'Belum diverifikasi Auditor.'], 400);

        DB::beginTransaction();
        try {
            $version->update([
                'status' => 'returned_to_auditor', 
                'auditor_verification_status' => 'revision', 
                'admin_notes' => $request->note, 
                'is_locked' => false 
            ]);
            $snap = $this->saveSnapshot($project, $version, 'returned_to_auditor');
            
            if ($request->has('tx_hash')) {
                $project->update(['tx_hash' => $request->tx_hash]);
            }
            DB::commit();

            return response()->json([
                'message'=>'Laporan dikembalikan ke Auditor',
                'dataHash' => $snap['dataHash'],
                'snapshotUri' => $snap['snapshotUri'],
                'snapshotId' => $snap['snapshotId'] // 👉 FIX DITAMBAHKAN
            ]);
        } catch (\Exception $e) { 
            DB::rollBack(); 
            return response()->json(['error' => $e->getMessage()], 500); 
        }
    }
    
    /**
     * Menampilkan daftar proyek yang siap dirilis ke market (sudah diaudit).
     */
    public function adminListingQueue()
    {
        $projects = Project::with(['activeVersion.provinsi', 'activeVersion.kota', 'activeVersion.kecamatan', 'activeVersion.kelurahan'])
            ->whereHas('activeVersion', function ($q) { 
                $q->where('auditor_verification_status','approved')->where('status','auditor_verified'); 
            })->latest()->get();
            
        return response()->json($projects);
    }

    /**
     * Menampilkan daftar proyek untuk panel Auditor yang siap diverifikasi.
     */
    public function auditorList()
    {
        $projects = Project::with([
            'issuer', 'activeVersion.documents', 'activeVersion.auditReport.auditor',
            'activeVersion.provinsi', 'activeVersion.kota', 'activeVersion.kecamatan', 'activeVersion.kelurahan', 
            'snapshots', // PASTIKAN RELASI INI ADA
            'versions' => function($query) { 
                $query->orderBy('version_number', 'desc')->with('documents'); 
            }
        ])->whereHas('activeVersion', function ($q) { 
            $q->whereIn('status', ['admin_approved', 'returned_to_auditor']); 
        })->latest()->paginate(10);
        
        return response()->json($projects);
    }
    
    /**
     * Proses Auditor melakukan verifikasi dan menghitung reduksi emisi karbon.
     */
    public function auditorVerify(Request $request, $projectId)
    {
        $request->validate([
            'calculation_method' => 'required|in:system_estimated,actual_inverter', 
            'baseline_emission_factor' => 'required|numeric|min:0',
            'verified_generation_kwh' => 'required_if:calculation_method,actual_inverter|nullable|numeric|min:0',
            'audit_notes' => 'nullable|string',
            'verification_checklist' => 'nullable|array', // 👉 FIX: Tangkap checklist
            'audit_documents.*' => 'nullable|mimes:pdf|max:10240', // 👉 FIX: Validasi file
            'audit_images.*' => 'nullable|image|max:5120'
        ]);
        
        $project = Project::with('activeVersion', 'activeVersion.auditReport')->findOrFail($projectId);
        $version = $project->activeVersion;

        if (!in_array($version->status, ['admin_approved', 'returned_to_auditor'])) {
            return response()->json(['message' => 'Proyek belum siap.'], 400);
        }

        $capacity = $version->total_system_capacity_kwp;
        $finalGenerationKwh = 0;

        // ==========================================
        // LOGIKA PERHITUNGAN MRV YANG BENAR
        // ==========================================
        if ($request->calculation_method === 'system_estimated') {
            $startDate = \Carbon\Carbon::parse($version->period_start);
            $endDate = \Carbon\Carbon::parse($version->period_end);
            $performanceRatio = 0.75; 

            $pshData = DB::table('psh_averages') 
                         ->where('kode_provinsi', $version->kode_provinsi)
                         ->first();

            if (!$pshData) {
                return response()->json(['message' => 'Gagal hitung otomatis. Data PSH tidak ditemukan.'], 422);
            }

            $totalPshAccumulated = 0;
            $currentDate = $startDate->copy();

            // Looping untuk mengakumulasikan PSH harian sesuai bulan berjalan
            while ($currentDate->lte($endDate)) {
                $monthColumn = strtolower($currentDate->format('M')); 
                $dailyPsh = $pshData->$monthColumn ?? 0;
                $totalPshAccumulated += $dailyPsh;
                $currentDate->addDay();
            }

            $finalGenerationKwh = $capacity * $totalPshAccumulated * $performanceRatio;
        } else {
            // Gunakan daya aktual jika Auditor memilih metode actual_inverter
            $finalGenerationKwh = $request->verified_generation_kwh;
        }

        // Hitung hasil akhir Tonase Karbon Reduksi
        $calculatedCarbonReduction = ($finalGenerationKwh / 1000) * $request->baseline_emission_factor;
        // ==========================================

        DB::beginTransaction();
        try {
            // 1. Simpan Data Laporan Audit
            if ($version->auditReport) {
                $version->auditReport->update([
                    'calculation_method' => $request->calculation_method, 
                    'verified_installed_capacity_kwp' => $capacity, 
                    'verified_generation_kwh' => $finalGenerationKwh, 
                    'baseline_emission_factor' => $request->baseline_emission_factor, 
                    'carbon_reduction_amount_ton' => $calculatedCarbonReduction, 
                    'audit_notes' => $request->audit_notes,
                    'verification_checklist' => $request->verification_checklist // 👉 FIX: Simpan checklist
                ]);
            } else {
                AuditReport::create([
                    'project_version_id' => $version->id, 
                    'auditor_id' => auth()->id(), 
                    'calculation_method' => $request->calculation_method, 
                    'verified_installed_capacity_kwp' => $capacity, 
                    'verified_generation_kwh' => $finalGenerationKwh, 
                    'baseline_emission_factor' => $request->baseline_emission_factor, 
                    'carbon_reduction_amount_ton' => $calculatedCarbonReduction, 
                    'audit_notes' => $request->audit_notes,
                    'verification_checklist' => $request->verification_checklist // 👉 FIX: Simpan checklist
                ]);
            }

            // ==============================================================
            // 👉 FOLDER DAN TIPE KHUSUS UNTUK AUDITOR
            // ==============================================================
            if ($request->hasFile('audit_documents')) {
                foreach ($request->file('audit_documents') as $file) {
                    // 👉 FOLDER KHUSUS AUDITOR DOCUMENT
                    $path = $file->store('projects/auditor_documents', 'public');
                    ProjectDocument::create([
                        'project_version_id' => $version->id, 
                        'type' => 'audit_report', 
                        'original_filename' => $file->getClientOriginalName(), 
                        'file_path' => $path, 
                        'uploader_role' => 'auditor'
                    ]);
                }
            }

            if ($request->hasFile('audit_images')) {
                foreach ($request->file('audit_images') as $file) {
                    // 👉 FOLDER KHUSUS AUDITOR IMAGE
                    $path = $file->store('projects/auditor_images', 'public');
                    ProjectDocument::create([
                        'project_version_id' => $version->id, 
                        'type' => 'audit_image', 
                        'original_filename' => $file->getClientOriginalName(), 
                        'file_path' => $path, 
                        'uploader_role' => 'auditor'
                    ]);
                }
            }
            // ==============================================================

            $version->update([
                'auditor_verification_status' => 'approved', 
                'status' => 'auditor_verified', 
                'is_locked' => true 
            ]);
            
            $snap = $this->saveSnapshot($project, $version, 'auditor_verified');
            
            if ($request->has('tx_hash')) {
                $project->update(['tx_hash' => $request->tx_hash]);
            }
            
            DB::commit();

            return response()->json([
                'message' => 'Auditor Verified.',
                'calculated_reduction' => $calculatedCarbonReduction,
                'dataHash' => $snap['dataHash'],
                'snapshotUri' => $snap['snapshotUri'],
                'snapshotId' => $snap['snapshotId']
            ]);
        } catch (\Exception $e) { 
            DB::rollBack(); 
            return response()->json(['error' => $e->getMessage()], 500); 
        }
    }

    /**
     * Aksi Auditor menolak laporan karena data yang dimasukkan Issuer salah/kurang.
     */
    public function auditorReject(Request $request, $projectId)
    {
        $request->validate(['note'=>'required|string']);
        $project = Project::with('activeVersion')->findOrFail($projectId);
        $version = $project->activeVersion;

        DB::beginTransaction();
        try {
            $version->update([
                'status'=>'rejected', 
                'auditor_verification_status'=>'rejected', 
                'auditor_notes'=>$request->note, 
                'is_locked'=>false
            ]);
            $snap = $this->saveSnapshot($project, $version, 'auditor_rejected');
            
            if ($request->has('tx_hash')) {
                $project->update(['tx_hash' => $request->tx_hash]);
            }
            DB::commit();

            return response()->json([
                'message'=>'Rejected by auditor',
                'dataHash' => $snap['dataHash'],
                'snapshotUri' => $snap['snapshotUri'],
                'snapshotId' => $snap['snapshotId'] // 👉 FIX DITAMBAHKAN
            ]);
        } catch (\Exception $e) { 
            DB::rollBack(); 
            return response()->json(['error' => $e->getMessage()], 500); 
        }
    }

    /**
     * Memanggil satu data snapshot spesifik berdasarkan ID snapshot.
     */
    public function getSnapshotById($id)
    {
        $snapshot = ProjectSnapshot::find($id);
        if (!$snapshot) return response()->json(['error' => 'Snapshot tidak ditemukan.'], 404);
        
        return response()->json([
            'metadata' => $snapshot->snapshot_data['metadata'], 
            'hash_info' => [
                'status' => $snapshot->status_at_snapshot, 
                'recorded_at' => $snapshot->created_at, 
                'expected_blockchain_hash' => $snapshot->data_hash
            ]
        ], 200, [], JSON_UNESCAPED_SLASHES);
    }

    /**
     * Memanggil data snapshot sebuah proyek berdasarkan status tertentu terakhir.
     */
    public function getSnapshotByStatus($projectId, $versionId, $status)
    {
        $snapshot = ProjectSnapshot::where('project_id', $projectId)
                        ->where('project_version_id', $versionId)
                        ->where('status_at_snapshot', $status)
                        ->latest('id')
                        ->first();
                        
        if (!$snapshot) return response()->json(['error' => 'Snapshot tidak ditemukan untuk status ini'], 404);
        
        return response()->json([
            'metadata' => $snapshot->snapshot_data['metadata'],
            'snapshotUri' => url("/api/snapshots/{$snapshot->id}"),
            'snapshotId' => $snapshot->id, // 👉 MENGIRIM ID KE FRONTEND
            'hash_info' => [
                'status' => $snapshot->status_at_snapshot, 
                'expected_blockchain_hash' => $snapshot->data_hash
            ]
        ], 200, [], JSON_UNESCAPED_SLASHES);
    }
    
    /**
     * Menyimpan Transaction Hash dari Blockchain ke database lokal.
     */
    public function saveTxHash(Request $request, $id)
    {
        $request->validate([
            'tx_hash' => 'nullable|string',       // Untuk riwayat status (ProjectSnapshot)
            'snapshot_id' => 'nullable', 
            'blockchain_tx' => 'nullable|string'  // 👉 SEKARANG REKATS SAMA KAYA KOLOM DB
        ]);

        // 1. Simpan Hash Pencetakan Token ke kolom blockchain_tx di tabel Project
        if ($request->has('blockchain_tx') && $request->blockchain_tx) {
            Project::where('id', $id)->update(['blockchain_tx' => $request->blockchain_tx]);
        }

        // 2. Simpan Hash Riwayat Status ke tabel ProjectSnapshot
        if ($request->has('tx_hash') && $request->tx_hash) {
            if ($request->has('snapshot_id') && $request->snapshot_id) {
                ProjectSnapshot::where('id', $request->snapshot_id)->update(['tx_hash' => $request->tx_hash]);
            } else {
                $latestSnapshot = ProjectSnapshot::where('project_id', $id)->orderBy('id', 'desc')->first();
                if ($latestSnapshot) {
                    $latestSnapshot->update(['tx_hash' => $request->tx_hash]);
                }
            }
        }

        return response()->json(['message' => 'Semua TxHash berhasil diamankan!']);
    }

    /**
     * Mengembalikan / Rollback status proyek jika transaksi Blockchain gagal (error).
     */
    public function revertStatus(Request $request, $id)
    {
        $request->validate(['previous_status' => 'required|string']);
        $project = Project::with('activeVersion.auditReport')->findOrFail($id);
        $version = $project->activeVersion;

        DB::beginTransaction();
        try {
            // Hapus catatan jika revert dari status reject/return
            if (in_array($version->status, ['returned_to_auditor', 'rejected'])) {
                $version->admin_notes = null;
            }
            if ($version->auditReport && in_array($version->status, ['auditor_verified', 'auditor_rejected'])) {
                $version->auditReport->update(['audit_notes' => null]);
            }

            // Atur kembali status dan kuncian dokumen
            $version->update([
                'status' => $request->previous_status, 
                'admin_verification_status' => $request->previous_status === 'submitted' ? 'pending' : $version->admin_verification_status, 
                'auditor_verification_status' => $request->previous_status === 'admin_approved' ? 'pending' : $version->auditor_verification_status, 
                'is_locked' => in_array($request->previous_status, ['submitted', 'admin_approved', 'auditor_verified']) ? true : false, 
                'admin_notes' => $version->admin_notes 
            ]);
            
            // Hapus snapshot yang gagal di-publish
            $latestSnapshot = ProjectSnapshot::where('project_version_id', $version->id)->latest('id')->first();
            if ($latestSnapshot && $latestSnapshot->status_at_snapshot !== $request->previous_status) {
                $latestSnapshot->delete();
            }
            
            DB::commit();
            return response()->json(['message' => 'Status berhasil dikembalikan ke ' . $request->previous_status]);
        } catch (\Exception $e) { 
            DB::rollBack(); 
            return response()->json(['error' => 'Gagal revert status: ' . $e->getMessage()], 500); 
        }
    }
}