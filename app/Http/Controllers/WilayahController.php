<?php

namespace App\Http\Controllers;

use App\Models\Wilayah;
use Illuminate\Http\Request;

class WilayahController extends Controller
{
    // Mengambil data Provinsi (Panjang kode = 2)
    public function getProvinsi()
    {
        $provinsi = Wilayah::whereRaw('LENGTH(kode) = 2')->orderBy('nama', 'asc')->get();
        return response()->json($provinsi);
    }

    // Mengambil data Kota berdasarkan awalan kode provinsi (Panjang kode = 5)
    public function getKota(Request $request)
    {
        $provinsiKode = $request->query('provinsi');
        if (!$provinsiKode) return response()->json([]);

        $kota = Wilayah::whereRaw('LENGTH(kode) = 5')
            ->where('kode', 'LIKE', $provinsiKode . '.%')
            ->orderBy('nama', 'asc')
            ->get();
            
        return response()->json($kota);
    }

    // Mengambil data Kecamatan berdasarkan awalan kode kota (Panjang kode = 8)
    public function getKecamatan(Request $request)
    {
        $kotaKode = $request->query('kota');
        if (!$kotaKode) return response()->json([]);

        $kecamatan = Wilayah::whereRaw('LENGTH(kode) = 8')
            ->where('kode', 'LIKE', $kotaKode . '.%')
            ->orderBy('nama', 'asc')
            ->get();
            
        return response()->json($kecamatan);
    }

    // Mengambil data Kelurahan berdasarkan awalan kode kecamatan (Panjang kode = 13)
    public function getKelurahan(Request $request)
    {
        $kecamatanKode = $request->query('kecamatan');
        if (!$kecamatanKode) return response()->json([]);

        $kelurahan = Wilayah::whereRaw('LENGTH(kode) = 13')
            ->where('kode', 'LIKE', $kecamatanKode . '.%')
            ->orderBy('nama', 'asc')
            ->get();
            
        return response()->json($kelurahan);
    }
}