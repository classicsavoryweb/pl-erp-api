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
        Schema::create('last_pay_deduction', function (Blueprint $table) {
            $table->id();
            $table->string("last_pay_id");
            $table->string("employee_id");
            $table->string("reference_doc");
            $table->string("deduction_id");
            $table->decimal("amount")->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('last_pay_deduction');
    }
};
