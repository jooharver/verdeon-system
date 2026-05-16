<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PshAverage;
use Illuminate\Support\Facades\DB;

class PshAverageSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Kumpulan Data PSH (Sudah dibulatkan 3 desimal berdasarkan CSV NASA)
        $datasets = [
            'aceh'      => [4.765, 5.223, 5.003, 4.622, 4.590, 4.717, 4.670, 4.610, 4.635, 4.406, 4.078, 4.410],
            'sumut'     => [4.317, 4.505, 4.933, 5.011, 4.844, 4.932, 4.968, 4.902, 4.787, 4.814, 4.683, 4.457],
            'sumbar'    => [4.495, 4.763, 4.921, 4.895, 4.689, 4.754, 4.802, 4.685, 4.584, 4.632, 4.559, 4.458], // Sumbar, Riau, Jambi, Bengkulu
            'kepri'     => [4.218, 4.735, 4.828, 4.641, 4.552, 4.242, 4.625, 4.564, 4.545, 4.493, 4.294, 4.109],
            'sumsel'    => [4.247, 4.435, 4.663, 4.618, 4.565, 4.464, 4.719, 4.749, 4.685, 4.572, 4.482, 4.248], // Sumsel, Babel, Lampung
            'banten'    => [4.885, 5.054, 5.273, 5.128, 4.741, 4.373, 4.778, 5.229, 5.487, 5.609, 5.077, 4.877], // Banten, DKI
            'jabar'     => [4.430, 4.397, 4.708, 4.710, 4.527, 4.377, 4.724, 5.082, 5.341, 5.141, 4.719, 4.492],
            'jateng'    => [4.586, 4.619, 5.109, 5.020, 4.753, 4.518, 5.031, 5.538, 5.849, 5.671, 5.121, 4.757], // Jateng, DIY
            'jatim'     => [4.307, 4.827, 5.146, 5.063, 4.834, 4.623, 5.058, 5.684, 6.008, 5.833, 5.150, 4.498],
            'bali'      => [5.094, 5.487, 5.614, 5.467, 5.254, 5.031, 5.380, 6.047, 6.519, 6.556, 5.846, 5.181], // Bali, NTB
            'ntt'       => [5.649, 5.825, 5.842, 5.866, 5.520, 5.087, 5.374, 6.117, 6.766, 7.055, 6.679, 5.963],
            'kalbar'    => [4.736, 5.342, 5.719, 5.470, 5.102, 4.974, 5.128, 5.244, 5.027, 4.772, 4.719, 4.630],
            'kalteng'   => [4.708, 4.848, 4.977, 5.048, 4.788, 4.685, 4.837, 4.838, 4.808, 4.711, 4.785, 4.794], // Kalteng, Kalsel
            'kaltim'    => [4.713, 4.938, 4.979, 4.924, 4.749, 4.491, 4.574, 4.665, 4.682, 4.805, 4.744, 4.756], // Kaltim, Kaltara
            'sulut'     => [4.745, 5.197, 5.370, 5.313, 4.954, 4.569, 4.788, 5.250, 5.538, 5.409, 5.146, 4.716], // Sulut, Gorontalo, Sulteng, Sultra
            'sulsel'    => [4.648, 5.055, 5.316, 5.358, 4.973, 4.652, 4.937, 5.305, 5.634, 5.413, 5.117, 4.735], // Sulsel, Sulbar
            'maluku'    => [5.398, 5.635, 5.771, 5.548, 5.195, 4.709, 4.799, 5.420, 5.860, 5.940, 5.666, 5.323], // Maluku, Malut
            'papua'     => [4.850, 4.976, 5.107, 4.929, 4.645, 4.367, 4.344, 4.714, 4.999, 4.994, 4.836, 4.560], // Papua, P. Pegunungan, P. Selatan
            'papuabrt'  => [5.334, 5.501, 5.739, 5.554, 5.298, 4.701, 4.700, 5.131, 5.433, 5.653, 5.494, 5.235], // Papua Barat, P. Barat Daya, P. Tengah
        ];

        // 2. Pemetaan ke 38 Kode Provinsi Resmi Indonesia
        $provinces = [
            ['kode' => '11', 'nama' => 'Aceh', 'set' => 'aceh'],
            ['kode' => '12', 'nama' => 'Sumatera Utara', 'set' => 'sumut'],
            ['kode' => '13', 'nama' => 'Sumatera Barat', 'set' => 'sumbar'],
            ['kode' => '14', 'nama' => 'Riau', 'set' => 'sumbar'],
            ['kode' => '15', 'nama' => 'Jambi', 'set' => 'sumbar'],
            ['kode' => '16', 'nama' => 'Sumatera Selatan', 'set' => 'sumsel'],
            ['kode' => '17', 'nama' => 'Bengkulu', 'set' => 'sumbar'],
            ['kode' => '18', 'nama' => 'Lampung', 'set' => 'sumsel'],
            ['kode' => '19', 'nama' => 'Kepulauan Bangka Belitung', 'set' => 'sumsel'],
            ['kode' => '21', 'nama' => 'Kepulauan Riau', 'set' => 'kepri'],
            ['kode' => '31', 'nama' => 'DKI Jakarta', 'set' => 'banten'],
            ['kode' => '32', 'nama' => 'Jawa Barat', 'set' => 'jabar'],
            ['kode' => '33', 'nama' => 'Jawa Tengah', 'set' => 'jateng'],
            ['kode' => '34', 'nama' => 'DI Yogyakarta', 'set' => 'jateng'],
            ['kode' => '35', 'nama' => 'Jawa Timur', 'set' => 'jatim'],
            ['kode' => '36', 'nama' => 'Banten', 'set' => 'banten'],
            ['kode' => '51', 'nama' => 'Bali', 'set' => 'bali'],
            ['kode' => '52', 'nama' => 'Nusa Tenggara Barat', 'set' => 'bali'],
            ['kode' => '53', 'nama' => 'Nusa Tenggara Timur', 'set' => 'ntt'],
            ['kode' => '61', 'nama' => 'Kalimantan Barat', 'set' => 'kalbar'],
            ['kode' => '62', 'nama' => 'Kalimantan Tengah', 'set' => 'kalteng'],
            ['kode' => '63', 'nama' => 'Kalimantan Selatan', 'set' => 'kalteng'],
            ['kode' => '64', 'nama' => 'Kalimantan Timur', 'set' => 'kaltim'],
            ['kode' => '65', 'nama' => 'Kalimantan Utara', 'set' => 'kaltim'],
            ['kode' => '71', 'nama' => 'Sulawesi Utara', 'set' => 'sulut'],
            ['kode' => '72', 'nama' => 'Sulawesi Tengah', 'set' => 'sulut'],
            ['kode' => '73', 'nama' => 'Sulawesi Selatan', 'set' => 'sulsel'],
            ['kode' => '74', 'nama' => 'Sulawesi Tenggara', 'set' => 'sulut'],
            ['kode' => '75', 'nama' => 'Gorontalo', 'set' => 'sulut'],
            ['kode' => '76', 'nama' => 'Sulawesi Barat', 'set' => 'sulsel'],
            ['kode' => '81', 'nama' => 'Maluku', 'set' => 'maluku'],
            ['kode' => '82', 'nama' => 'Maluku Utara', 'set' => 'maluku'],
            ['kode' => '91', 'nama' => 'Papua', 'set' => 'papua'],
            ['kode' => '92', 'nama' => 'Papua Barat', 'set' => 'papuabrt'],
            ['kode' => '93', 'nama' => 'Papua Selatan', 'set' => 'papua'],
            ['kode' => '94', 'nama' => 'Papua Tengah', 'set' => 'papuabrt'],
            ['kode' => '95', 'nama' => 'Papua Pegunungan', 'set' => 'papua'],
            ['kode' => '96', 'nama' => 'Papua Barat Daya', 'set' => 'papuabrt'],
        ];

        // 3. Eksekusi Pengisian ke Database
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        PshAverage::truncate(); // Bersihkan isi tabel sebelum di-seed
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        foreach ($provinces as $prov) {
            $data = $datasets[$prov['set']];
            
            PshAverage::create([
                'kode_provinsi' => $prov['kode'],
                'nama_provinsi' => $prov['nama'],
                'jan' => $data[0],
                'feb' => $data[1],
                'mar' => $data[2],
                'apr' => $data[3],
                'may' => $data[4],
                'jun' => $data[5],
                'jul' => $data[6],
                'aug' => $data[7],
                'sep' => $data[8],
                'oct' => $data[9],
                'nov' => $data[10],
                'dec' => $data[11],
            ]);
        }
        
        $this->command->info('Data PSH NASA berhasil di-seed untuk 38 Provinsi!');
    }
}