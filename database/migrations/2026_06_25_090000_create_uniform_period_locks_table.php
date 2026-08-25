<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uniform_period_locks', function (Blueprint $table) {
            $table->id();
            $table->string('period_name');
            $table->date('date_from');
            $table->date('date_to');
            $table->string('status')->default('active')->index();
            $table->string('prepared_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->index(['date_from', 'date_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uniform_period_locks');
    }
};
