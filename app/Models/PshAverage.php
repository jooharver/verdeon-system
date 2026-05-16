<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PshAverage extends Model
{
    use HasFactory;

    protected $table = 'psh_averages';

    protected $fillable = [
        'kode_provinsi',
        'nama_provinsi',
        'jan', 'feb', 'mar', 'apr', 'may', 'jun',
        'jul', 'aug', 'sep', 'oct', 'nov', 'dec', 'annual'
    ];

    /**
     * Relasi ke tabel Wilayah (hanya ke Provinsinya saja)
     */
    public function provinsi()
    {
        // belongsTo(ModelTujuan, 'foreign_key_di_tabel_ini', 'owner_key_di_tabel_tujuan')
        return $this->belongsTo(Wilayah::class, 'kode_provinsi', 'kode');
    }
}