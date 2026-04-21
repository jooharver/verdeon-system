<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectVersion extends Model
{
    protected $fillable = [

        'project_id',
        'version_number',

        'name',
        'description',
        'location_country',
        'location_province',
        'location_city',
        'address',
        'project_type',

        'status',
        'admin_verification_status',
        'auditor_verification_status',
        'is_locked',
        'auditor_id',
        'admin_notes',
        'auditor_notes'
    ];

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
}