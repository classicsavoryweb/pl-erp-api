<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uniform_replenishments', function (Blueprint $table) {
            $table->id();
            $table->string('request_no')->unique();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->string('replenishment_type')->default('Purchasing Department');
            $table->date('requested_date');
            $table->string('status')->default('Unposted')->index();
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->string('prepared_by')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->string('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancelled_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('uniform_replenishment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('replenishment_id')->constrained('uniform_replenishments')->cascadeOnDelete();
            $table->foreignId('uniform_item_id')->constrained('uniform_items');
            $table->decimal('requested_qty', 12, 2);
            $table->decimal('approved_qty', 12, 2);
            $table->decimal('received_qty', 12, 2)->default(0);
            $table->decimal('unit_price', 12, 2);
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->unique(['replenishment_id', 'uniform_item_id'], 'uniform_rpl_item_unique');
        });

        Schema::create('uniform_receivings', function (Blueprint $table) {
            $table->id();
            $table->string('receiving_no')->unique();
            $table->foreignId('replenishment_id')->constrained('uniform_replenishments');
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->date('date_received');
            $table->string('crf_no')->unique();
            $table->string('document_no')->unique();
            $table->string('checker_name');
            $table->string('transmitted_by')->nullable();
            $table->string('status')->default('Unposted')->index();
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->string('prepared_by')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->string('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancelled_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('uniform_receiving_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receiving_id')->constrained('uniform_receivings')->cascadeOnDelete();
            $table->foreignId('replenishment_item_id')->constrained('uniform_replenishment_items');
            $table->foreignId('uniform_item_id')->constrained('uniform_items');
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_price', 12, 2);
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->unique(['receiving_id', 'replenishment_item_id'], 'uniform_ri_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uniform_receiving_items');
        Schema::dropIfExists('uniform_receivings');
        Schema::dropIfExists('uniform_replenishment_items');
        Schema::dropIfExists('uniform_replenishments');
    }
};
