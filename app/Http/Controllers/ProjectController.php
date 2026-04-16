<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\ProjectDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\ProjectVersion;

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
        ]);

        DB::beginTransaction();

        try {

            // CREATE PROJECT IDENTITY
            $project = Project::create([
                'issuer_id' => Auth::id(),
            ]);

            // CREATE VERSION 1
            $version = \App\Models\ProjectVersion::create([
                'project_id' => $project->id,
                'version_number' => 1,

                'name' => $request->name,
                'description' => $request->description,

                'location_country' => $request->location_country,
                'location_province' => $request->location_province,
                'location_city' => $request->location_city,
                'address' => $request->address,

                'status' => 'draft',
                'admin_verification_status' => 'pending',
                'auditor_verification_status' => 'pending',
                'admin_notes'=>null,
                'auditor_notes'=>null,
                'is_locked' => false,

            ]);

            $project->update([
                'active_version_id'=>$version->id
            ]);

            DB::commit();

            return response()->json([
                'message'=>'Project V1 created',
                'project'=>$project,
                'version'=>$version
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'error'=>$e->getMessage()
            ],500);
        }
    }

    //ISSUER UPDATE PROJECT (HANYA DRAFT)
    public function update(Request $request, $id)
    {
        $project = Project::findOrFail($id);
        $version = $project->activeVersion;

        if ($project->issuer_id !== auth()->id()) {
            return response()->json(['message'=>'Unauthorized'],403);
        }

        if ($version->status !== 'draft') {
            return response()->json([
                'message'=>'Only draft version editable'
            ],403);
        }

        $version->update($request->only([
            'name',
            'description',
            'location_country',
            'location_province',
            'location_city',
            'address'
        ]));

        return response()->json([
            'message'=>'Draft version updated',
            'version'=>$version
        ]);
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
        $projects = Project::with('activeVersion')
            ->where('issuer_id', auth()->id())
            ->latest()
            ->get();

        return response()->json($projects);
    }

    //ISSUER SHOW Project By Id
    public function show($id)
    {
        $project = Project::with('activeVersion')
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

    //ISSUER REVISE
    public function reviseProject($projectId)
    {
        $project = Project::with('activeVersion')->findOrFail($projectId);

        $oldVersion = $project->activeVersion;

        if ($project->issuer_id !== auth()->id()) {
            return response()->json(['message'=>'Unauthorized'],403);
        }

        if ($oldVersion->status !== 'rejected') {
            return response()->json([
                'message' => 'Project not eligible for revision'
            ],400);
        }

        DB::beginTransaction();

        try {

            $newVersion = ProjectVersion::create([
                'project_id' => $project->id,
                'version_number' => $oldVersion->version_number + 1,

                'name' => $oldVersion->name,
                'description' => $oldVersion->description,

                'location_country'=>$oldVersion->location_country,
                'location_province'=>$oldVersion->location_province,
                'location_city'=>$oldVersion->location_city,
                'address'=>$oldVersion->address,

                'status'=>'draft',
                'admin_verification_status'=>'pending',
                'auditor_verification_status'=>'pending',
                'is_locked'=>false
            ]);

            $project->update([
                'active_version_id'=>$newVersion->id
            ]);

            DB::commit();

            return response()->json([
                'message'=>'Revision created',
                'version'=>$newVersion
            ]);

        } catch (\Exception $e){

            DB::rollBack();
            return response()->json(['error'=>$e->getMessage()],500);
        }
    }

    // ===============================
    // ADMIN LIST PROJECTS
    // ===============================
    public function adminList()
    {
        // 1. Tambahkan 'issuer' ke dalam with() agar frontend bisa membaca nama perusahaan/issuer
        // 2. Ubah query agar admin bisa melihat SEMUA proyek, kecuali mungkin yang masih 'draft' murni di sisi issuer
        $projects = Project::with(['activeVersion', 'issuer'])
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
        $projects = Project::with('activeVersion')
            ->whereHas('activeVersion', function ($q) {
                $q->where('admin_verification_status','approved')
                ->where('auditor_verification_status','pending');
            })
            ->latest()
            ->get();

        return response()->json($projects);
    }
    // ===============================
    // AUDITOR VERIFY
    // ===============================
    public function auditorVerify($projectId)
    {
        $project = Project::with('activeVersion')->findOrFail($projectId);
        $version = $project->activeVersion;

        if ($version->status !== 'admin_approved') {
            return response()->json([
                'message'=>'Version not ready for auditor'
            ],400);
        }

        $version->update([
            'auditor_verification_status'=>'approved',
            'status'=>'auditor_verified'
        ]);

        return response()->json([
            'message'=>'Auditor verified'
        ]);
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