<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_snapshots', function (Blueprint $table) {
            // Tambahkan kolom tx_hash setelah data_hash
            $table->string('tx_hash')->nullable()->after('data_hash');
        });
    }

    public function down(): void
    {
        Schema::table('project_snapshots', function (Blueprint $table) {
            $table->dropColumn('tx_hash');
        });
    }
};