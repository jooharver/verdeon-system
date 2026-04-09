<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectDocument extends Model
{
    protected $fillable = [
        'project_version_id',
        'type',
        'original_filename',
        'file_path',
        'uploader_role'
    ];

    public function version()
    {
        return $this->belongsTo(ProjectVersion::class,'project_version_id');
    }
}