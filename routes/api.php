<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\MetadataController;
use App\Http\Controllers\WilayahController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// ==========================================
// DATA LOKASI (CASCADING DROPDOWN)
// ==========================================
Route::get('/wilayah/provinsi', [WilayahController::class, 'getProvinsi']);
Route::get('/wilayah/kota', [WilayahController::class, 'getKota']);
Route::get('/wilayah/kecamatan', [WilayahController::class, 'getKecamatan']);
Route::get('/wilayah/kelurahan', [WilayahController::class, 'getKelurahan']);

// ==========================================
// PUBLIC ROUTES (WEB3 VERIFICATION)
// ==========================================
Route::get('/projects/{project}/versions/{version}/metadata', [MetadataController::class, 'generateMetadata']);

// 👇 INI ROUTE SNAPSHOT BARU YANG DITAMBAHKAN 👇
Route::get('/projects/{projectId}/versions/{versionId}/snapshot/{status}', [ProjectController::class, 'getSnapshot']);

//Route untuk menampilkan semua proyek yang sudah listing di market tanpa mempedulikan role
Route::get('/market/projects', [App\Http\Controllers\ProjectController::class, 'getMarketProjects']);

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::patch('/profile',[AuthController::class,'updateProfile']);
    Route::get('/projects/{id}',[ProjectController::class,'show']);
    Route::get('/projects/{id}/versions',[ProjectController::class,'versions']);
    Route::get('/projects/{projectId}/versions/{versionId}',[ProjectController::class,'showVersion']);
    Route::post('/projects/{id}/save-tx', [ProjectController::class, 'saveTxHash']);

    // ISSUER ONLY
    Route::middleware('role:issuer')->group(function () {
        Route::get('/issuer/dashboard', fn() => response()->json([
            'message' => 'Issuer access granted'
        ]));
        Route::post('/projects',[ProjectController::class,'store']);
        Route::patch('/projects/{id}',[ProjectController::class,'update']);
        Route::post('/projects/{id}/submit',[ProjectController::class,'submit']);
        Route::get('/issuer/projects',[ProjectController::class,'issuerProjects']);
        Route::post('/projects/{id}/revise',[ProjectController::class,'reviseProject']);
        Route::delete('/projects/{id}',[ProjectController::class,'destroy']);
    });

    // ADMIN ONLY
    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/dashboard', fn() => response()->json([
            'message' => 'Admin access granted'
        ]));
        // USER MANAGEMENT
        Route::post('/admin/users',[UserController::class,'store']);
        Route::get('/admin/users',[UserController::class,'index']);
        Route::delete('/admin/users/{id}',[UserController::class,'destroy']);
        //Project Management
        Route::get('/admin/projects',[ProjectController::class,'adminList']);
        Route::post('/admin/projects/{id}/approve',[ProjectController::class,'adminApprove']);
        Route::post('/admin/projects/{id}/reject',[ProjectController::class,'adminReject']);
        Route::post('/admin/projects/{id}/list',[ProjectController::class,'adminListProject']);
        Route::get('/admin/projects/listing-queue',[ProjectController::class,'adminListingQueue']);
    });

    // AUDITOR ONLY
    Route::middleware('role:auditor')->group(function () {
        Route::get('/auditor/dashboard', fn() => response()->json([
            'message' => 'Auditor access granted'
        ]));
        
        Route::get('/auditor/projects',[ProjectController::class,'auditorList']);

        Route::post('/auditor/projects/{id}/verify',
            [ProjectController::class,'auditorVerify']
        );

        Route::post('/auditor/projects/{id}/reject',
            [ProjectController::class,'auditorReject']
        );
        
    });
});