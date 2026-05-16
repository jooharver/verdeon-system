<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'project_version_id',
        'status_at_snapshot',
        'snapshot_data',
        'data_hash',
    ];

    protected $casts = [
        'snapshot_data' => 'array', // Otomatis handle JSON ke Array dan sebaliknya
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function version()
    {
        return $this->belongsTo(ProjectVersion::class, 'project_version_id');
    }
}