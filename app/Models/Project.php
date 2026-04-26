<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = [
        'issuer_id',
        'active_version_id',
        'tx_hash',
    ];

    public function issuer()
    {
        return $this->belongsTo(User::class,'issuer_id');
    }

    public function versions()
    {
        return $this->hasMany(ProjectVersion::class);
    }

    public function activeVersion()
    {
        return $this->belongsTo(ProjectVersion::class,'active_version_id');
    }
}