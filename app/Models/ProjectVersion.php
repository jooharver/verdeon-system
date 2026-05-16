<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectVersion extends Model
{
    protected $fillable = [
        'project_id',
        'version_number',

        // General Info
        'name',
        'description',
        'kode_provinsi',
        'kode_kota',
        'kode_kecamatan',
        'kode_kelurahan',
        'address',
        'project_type',

        // Technical Specs
        'total_system_capacity_kwp',
        'inverter_capacity_kw',
        'installation_date',
        'panel_brand',
        'inverter_brand',

        // Claim Period (Baru)
        'period_start',
        'period_end',

        // Status & Workflow
        'status',
        'admin_verification_status',
        'auditor_verification_status',
        'is_locked',
        'auditor_id',
        'admin_notes',
        'auditor_notes'
    ];

    // Casting agar tipe datanya dibaca sebagai format Date oleh Laravel
    protected $casts = [
        'installation_date' => 'date',
        'period_start' => 'date',
        'period_end' => 'date',
    ];

    // --- RELASI KE ENTITAS PROYEK ---

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function documents()
    {
        return $this->hasMany(ProjectDocument::class);
    }

    public function auditReport()
    {
        return $this->hasOne(AuditReport::class, 'project_version_id');
    }

    // --- RELASI KE DATA WILAYAH ---

    public function provinsi()
    {
        return $this->belongsTo(Wilayah::class, 'kode_provinsi', 'kode');
    }

    public function kota()
    {
        return $this->belongsTo(Wilayah::class, 'kode_kota', 'kode');
    }

    public function kecamatan()
    {
        return $this->belongsTo(Wilayah::class, 'kode_kecamatan', 'kode');
    }

    public function kelurahan()
    {
        return $this->belongsTo(Wilayah::class, 'kode_kelurahan', 'kode');
    }
}