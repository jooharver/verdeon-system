<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditReport extends Model
{
    // Mendaftarkan kolom-kolom agar bisa diisi (Mass Assignment)
    protected $fillable = [
        'project_version_id',
        'auditor_id',
        'calculation_method',
        'verified_installed_capacity_kwp',
        'verified_generation_kwh',
        'baseline_emission_factor',
        'carbon_reduction_amount_ton',
        'verification_checklist',
        'onsite_measurement_date',
        'audit_notes',
    ];

    // Mengubah JSON otomatis menjadi Array di Laravel
    protected $casts = [
        'verification_checklist' => 'array',
        'onsite_measurement_date' => 'date',
    ];

    // Relasi ke versi proyek yang diaudit
    public function projectVersion()
    {
        return $this->belongsTo(ProjectVersion::class);
    }

    // Relasi ke auditor yang melakukan audit
    public function auditor()
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }
}