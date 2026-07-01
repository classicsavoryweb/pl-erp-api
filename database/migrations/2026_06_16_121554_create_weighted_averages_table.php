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
        Schema::create('weighted_averages', function (Blueprint $table) {
            $table->id();
            $table->string('weighted_id')->unique();
            $table->string('tag');
            $table->date('date_posted');
            $table->date('date_from');
            $table->date('date_to');
            $table->string('status')->default('Unposted')->index();
            $table->string('overall_remarks')->nullable();
            $table->string('prepared_by')->nullable();
            $table->string('prepared_at')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->string('reviewed_at')->nullable();
            $table->string('posted_by')->nullable();
            $table->string('posted_at')->nullable();
            $table->string('cancelled_by')->nullable();
            $table->string('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('weighted_average_items', function (Blueprint $table) {
            $table->id();
            $table->string('weighted_id_number');
            $table->string('uniform_item_no');
            $table->string('description');
            $table->string('standard_uom')->nullable();
            $table->decimal('current_price', 12, 2)->default(0);
            $table->decimal('total_quantity', 12, 2)->default(0);
            $table->string('standard_uom_two')->nullable();
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->decimal('new_standard_price', 12, 2)->default(0);
            $table->decimal('other_charges', 12, 2)->default(0);
            $table->decimal('standard_difference', 12, 2)->default(0);
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->foreign('weighted_id_number')
                ->references('weighted_id')
                ->on('weighted_averages')
                ->cascadeOnDelete();
            $table->index('uniform_item_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weighted_average_items');
        Schema::dropIfExists('weighted_averages');
    }
};
