<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditReport extends Model
{
    use HasFactory;

    // Menentukan kolom mana saja yang boleh diisi (mass assignable)
    protected $fillable = [
        'project_version_id',
        'auditor_id',
        'verified_installed_capacity_kwp',
        'verified_annual_generation_kwh',
        'baseline_emission_factor',
        'expected_carbon_reduction_ton_per_year',
        'onsite_measurement_date',
        'audit_notes',
    ];

    /**
     * Relasi ke versi proyek yang diaudit
     */
    public function version()
    {
        return $this->belongsTo(ProjectVersion::class, 'project_version_id');
    }

    /**
     * Relasi ke user yang menjadi auditor
     */
    public function auditor()
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }
}