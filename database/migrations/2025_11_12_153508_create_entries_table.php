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
        Schema::create('entries', function (Blueprint $table) {
            $table->id();
            $table->string("entry_id")->nullable();
            $table->string("branch_id")->nullable();
            $table->string("branch_number")->nullable();
            $table->decimal("amount", 13, 2);
            $table->string("vendor_no")->nullable();
            $table->string("account_no")->nullable();
            $table->string("description");
            $table->string("balance_type");
            $table->boolean("is_block_payment")->default(false);
            $table->string("status");
            $table->string("generated_at")->nullable();
            $table->string("generated_by")->nullable();
            $table->string("posted_at")->nullable();
            $table->string("posted_by")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entries');
    }
};
