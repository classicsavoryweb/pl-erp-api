<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uniform_physical_counts', function (Blueprint $table) {
            $table->id();
            $table->string('physical_count_no')->unique();
            $table->date('begin_date');
            $table->date('ending_date');
            $table->string('counted_by')->nullable();
            $table->string('status')->default('Unposted')->index();
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

            $table->index(['begin_date', 'ending_date']);
        });

        Schema::create('uniform_physical_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('physical_count_id')->constrained('uniform_physical_counts')->cascadeOnDelete();
            $table->foreignId('uniform_item_id')->constrained('uniform_items');
            $table->string('item_no');
            $table->string('description');
            $table->string('uom')->nullable();
            $table->decimal('beginning_inventory', 12, 2)->default(0);
            $table->decimal('purchasing_department', 12, 2)->default(0);
            $table->decimal('made_to_order', 12, 2)->default(0);
            $table->decimal('atd', 12, 2)->default(0);
            $table->decimal('free_uniform', 12, 2)->default(0);
            $table->decimal('total_purchases', 12, 2)->default(0);
            $table->decimal('total_outgoing', 12, 2)->default(0);
            $table->decimal('should_be_inventory', 12, 2)->default(0);
            $table->decimal('physical_count', 12, 2)->default(0);
            $table->decimal('standard_cost', 12, 2)->default(0);
            $table->text('item_remarks')->nullable();
            $table->timestamps();

            $table->unique(['physical_count_id', 'uniform_item_id'], 'uniform_pc_item_unique');
        });

        Schema::create('physical_count_compare_table', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uniform_item_id')->constrained('uniform_items');
            $table->foreignId('physical_count_id')->nullable()->constrained('uniform_physical_counts')->nullOnDelete();
            $table->string('item_no');
            $table->date('beginning_date');
            $table->decimal('stock_on_hand', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['uniform_item_id', 'beginning_date'], 'physical_count_compare_item_date_unique');
        });

        Schema::create('uniform_variances', function (Blueprint $table) {
            $table->id();
            $table->string('variance_no')->unique();
            $table->foreignId('physical_count_id')->constrained('uniform_physical_counts');
            $table->date('begin_date');
            $table->date('ending_date');
            $table->string('status')->default('Unposted')->index();
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

            $table->index(['begin_date', 'ending_date']);
        });

        Schema::create('uniform_variance_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variance_id')->constrained('uniform_variances')->cascadeOnDelete();
            $table->foreignId('uniform_item_id')->constrained('uniform_items');
            $table->string('item_no');
            $table->string('description');
            $table->string('uom')->nullable();
            $table->decimal('beginning_inventory', 12, 2)->default(0);
            $table->decimal('purchasing_department', 12, 2)->default(0);
            $table->decimal('made_to_order', 12, 2)->default(0);
            $table->decimal('atd', 12, 2)->default(0);
            $table->decimal('free_uniform', 12, 2)->default(0);
            $table->decimal('should_be_inventory', 12, 2)->default(0);
            $table->decimal('physical_count', 12, 2)->default(0);
            $table->decimal('variance', 12, 2)->default(0);
            $table->decimal('adjustment', 12, 2)->default(0);
            $table->decimal('final_variance', 12, 2)->default(0);
            $table->decimal('standard_cost', 12, 2)->default(0);
            $table->decimal('total_variance_cost', 14, 2)->default(0);
            $table->text('reason_of_adjustment')->nullable();
            $table->timestamps();

            $table->unique(['variance_id', 'uniform_item_id'], 'uniform_variance_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uniform_variance_items');
        Schema::dropIfExists('uniform_variances');
        Schema::dropIfExists('physical_count_compare_table');
        Schema::dropIfExists('uniform_physical_count_items');
        Schema::dropIfExists('uniform_physical_counts');
    }
};
