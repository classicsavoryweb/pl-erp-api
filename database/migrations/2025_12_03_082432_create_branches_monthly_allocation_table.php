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
        Schema::create('branches_monthly_allocation', function (Blueprint $table) {
            $table->id();
            $table->string("thirteenth_month_file_id");
            $table->string("branch_id");
            $table->decimal("branch_net_claim_13th_month", 13, 2);
            $table->decimal("branch_net_claim_pay_leave", 13, 2);
            $table->decimal("branch_net_claim_additional", 13, 2);
            $table->decimal("branch_total", 13, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches_monthly_allocation');
    }
};
