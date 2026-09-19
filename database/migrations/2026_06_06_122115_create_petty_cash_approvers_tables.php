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
        Schema::create('petty_cash_approvers', function (Blueprint $table) {
            $table->id();
            $table->string('description');
            $table->boolean('use_same_range')->default(true);
            $table->decimal('min_amount', 13, 2)->nullable();
            $table->decimal('max_amount', 13, 2)->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('petty_cash_approver_employee', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approver_id')->constrained('petty_cash_approvers')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->decimal('min_amount', 13, 2)->nullable();
            $table->decimal('max_amount', 13, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('petty_cash_approver_branch', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approver_id')->constrained('petty_cash_approvers')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('petty_cash_approver_branch');
        Schema::dropIfExists('petty_cash_approver_employee');
        Schema::dropIfExists('petty_cash_approvers');
    }
};
