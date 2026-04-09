<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_documents', function (Blueprint $table) {

            $table->id();

            $table->foreignId('project_version_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->enum('type',['image','document']);

            $table->string('original_filename');
            $table->string('file_path');

            $table->enum('uploader_role',[
                'issuer','admin','auditor'
            ]);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_documents');
    }
};