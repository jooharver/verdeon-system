<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\UserController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/projects',[ProjectController::class,'store']);

    Route::patch('/profile',[AuthController::class,'updateProfile']);

    // ISSUER ONLY
    Route::middleware('role:issuer')->group(function () {
        Route::get('/issuer/dashboard', fn() => response()->json([
            'message' => 'Issuer access granted'
        ]));
        Route::patch('/projects/{id}',[ProjectController::class,'update']);
        Route::post('/projects/{id}/submit',[ProjectController::class,'submit']);
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