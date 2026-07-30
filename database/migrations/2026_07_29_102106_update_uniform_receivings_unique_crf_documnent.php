<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('uniform_receivings', function (Blueprint $table) {
            $table->dropUnique('uniform_receivings_crf_no_unique');
            $table->dropUnique('uniform_receivings_document_no_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('uniform_receivings', function (Blueprint $table) {
            $table->unique('crf_no','uniform_receivings_crf_no_unique');
            $table->unique('document_no','uniform_receivings_document_no_unique');
            
        });
    }
};
