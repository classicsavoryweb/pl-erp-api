<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petty_cash_liquidations', function (Blueprint $table) {
            $table->id();
            $table->string('liquidation_id');
            $table->foreignId('petty_cash_issuance_id')->constrained('petty_cash_issuances')->onDelete('cascade');
            $table->date('date');
            $table->string('liquidation_type');
            $table->string('requested_by_employee_number');
            $table->string('reference_document_number', 50);
            $table->date('reference_document_date');
            $table->text('description');
            $table->decimal('amount', 13, 2);
            $table->foreignId('expense_type_id')->constrained('expense_types');
            $table->foreignId('created_by_id')->nullable()->constrained('users');
            $table->foreignId('updated_by_id')->nullable()->constrained('users');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_liquidations');
    }
};
