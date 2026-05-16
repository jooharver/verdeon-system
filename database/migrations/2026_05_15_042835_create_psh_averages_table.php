<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('psh_averages', function (Blueprint $table) {
            $table->id();
            
            // Kolom penghubung ke tabel wilayah (misal "11" untuk Aceh)
            // Pakai unique() agar 1 provinsi hanya punya 1 data PSH
            $table->string('kode_provinsi', 2)->unique(); 
            $table->string('nama_provinsi')->nullable(); // Opsional untuk memudahkan baca data
            
            // Data GHI/PSH bulanan (5 digit total, 3 di belakang koma, misal: 4.955)
            $table->decimal('jan', 5, 3);
            $table->decimal('feb', 5, 3);
            $table->decimal('mar', 5, 3);
            $table->decimal('apr', 5, 3);
            $table->decimal('may', 5, 3);
            $table->decimal('jun', 5, 3);
            $table->decimal('jul', 5, 3);
            $table->decimal('aug', 5, 3);
            $table->decimal('sep', 5, 3);
            $table->decimal('oct', 5, 3);
            $table->decimal('nov', 5, 3);
            $table->decimal('dec', 5, 3);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psh_averages');
    }
};