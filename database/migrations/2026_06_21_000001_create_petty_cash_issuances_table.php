<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petty_cash_issuances', function (Blueprint $table) {
            $table->id();
            $table->string('pcv_number')->unique();
            $table->date('date');
            $table->unsignedBigInteger('branch_id');
            $table->string('requested_by_employee_number');
            $table->string('received_by_employee_number');
            $table->decimal('amount', 13, 2);
            $table->text('detailed_purpose');
            $table->string('status')->default('Unliquidated');
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->timestamps();

            $table->foreign('branch_id', 'pci_branch_fk')
                ->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('created_by_id', 'pci_created_by_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by_id', 'pci_updated_by_fk')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_issuances');
    }
};
