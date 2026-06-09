<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uniform_genders', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('description')->unique();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('uniform_items', function (Blueprint $table) {
            $table->id();
            $table->string('item_no')->unique();
            $table->string('description');
            $table->foreignId('uniform_size_id')->constrained('sizes');
            $table->foreignId('uniform_gender_id')->constrained('uniform_genders');
            $table->decimal('standard_unit_price', 12, 2)->default(0);
            $table->decimal('stock_on_hand', 12, 2)->default(0);
            $table->string('status')->default('active');
            $table->decimal('standard_qty', 12, 2)->default(1);
            $table->string('standard_unit')->nullable();
            $table->decimal('min_order', 12, 2)->default(1);
            $table->decimal('order_limit', 12, 2)->default(1);
            $table->decimal('crit_level', 12, 2)->default(1);
            $table->decimal('minimum_rpl', 12, 2)->default(1);
            $table->boolean('free_uniform')->default(false);
            $table->boolean('made_to_order')->default(false);
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('uniform_item_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uniform_item_id')->constrained('uniform_items')->cascadeOnDelete();
            $table->string('vendor_no');
            $table->string('vendor_name');
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->string('packaging_unit')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->unique(['uniform_item_id', 'vendor_no']);
            $table->index('vendor_no');
        });

        $now = now();
        foreach ([
            ['code' => 'MALE', 'description' => 'Male'],
            ['code' => 'FEMALE', 'description' => 'Female'],
            ['code' => 'UNISEX', 'description' => 'Unisex'],
        ] as $gender) {
            DB::table('uniform_genders')->insert([
                'code' => $gender['code'],
                'description' => $gender['description'],
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('uniform_item_suppliers');
        Schema::dropIfExists('uniform_items');
        Schema::dropIfExists('uniform_genders');
    }
};
