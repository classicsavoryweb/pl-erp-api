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
        Schema::create('last_pay_additional_fields', function (Blueprint $table) {
            $table->id();
            $table->string("last_pay_id");
            $table->string("transaction_type_id");
            $table->decimal("amount", 10);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('last_pay_additional_fields');
    }
};
