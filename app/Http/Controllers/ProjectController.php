<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\ProjectDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

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

                'status' => 'draft'
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
            'is_locked'=>true
        ]);

        return response()->json([
            'message'=>'Version submitted',
            'version'=>$version
        ]);
    }

    // ===============================
    // ADMIN LIST PROJECTS
    // ===============================
    public function adminList()
    {
        $projects = Project::with('activeVersion')
            ->whereHas('activeVersion', function ($q) {
                $q->where('admin_verification_status','pending')
                ->where('status','submitted');
            })
            ->latest()
            ->get();

        return response()->json($projects);
    }

    // ===============================
    // ADMIN APPROVE
    // ===============================
    public function adminApprove($id)
    {
        $project = Project::findOrFail($id);
        $version = $project->activeVersion;

        if ($version->status !== 'submitted') {
            return response()->json([
                'message'=>'Version not ready for admin approval'
            ],403);
        }

        $version->update([
            'admin_verification_status'=>'approved',
            'status'=>'admin_approved'
        ]);

        return response()->json([
            'message'=>'Admin approved version',
            'version'=>$version
        ]);
    }

    // ===============================
    // ADMIN REJECT
    // ===============================
    public function adminReject($id)
    {
        $project = Project::findOrFail($id);
        $version = $project->activeVersion;

        $version->update([
            'admin_verification_status'=>'rejected',
            'status'=>'admin_rejected',
            'is_locked'=>false
        ]);

        return response()->json([
            'message'=>'Version rejected by admin',
            'version'=>$version
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
    public function auditorVerify($id)
    {
        $project = Project::findOrFail($id);
        $version = $project->activeVersion;

        if ($version->admin_verification_status !== 'approved') {
            return response()->json([
                'message'=>'Admin approval required'
            ],403);
        }

        $version->update([
            'auditor_verification_status'=>'approved',
            'status'=>'auditor_verified'
        ]);

        return response()->json([
            'message'=>'Version verified by auditor',
            'version'=>$version
        ]);
    }
    // ===============================
    // AUDITOR REJECT
    // ===============================
    public function auditorReject($id)
    {
        $project = Project::findOrFail($id);
        $version = $project->activeVersion;

        $version->update([
            'auditor_verification_status'=>'rejected',
            'status'=>'auditor_rejected',
            'is_locked'=>false
        ]);

        return response()->json([
            'message'=>'Version rejected by auditor',
            'version'=>$version
        ]);
    }
}