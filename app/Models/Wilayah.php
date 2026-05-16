<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wilayah extends Model
{
    protected $table = 'wilayah';
    protected $primaryKey = 'kode';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    /**
     * Relasi ke data PSH (Hanya berlaku untuk level Provinsi/2 digit)
     */
    public function pshData()
    {
        // hasOne(ModelTujuan, 'foreign_key_di_tabel_tujuan', 'local_key_di_tabel_ini')
        return $this->hasOne(PshAverage::class, 'kode_provinsi', 'kode');
    }
}