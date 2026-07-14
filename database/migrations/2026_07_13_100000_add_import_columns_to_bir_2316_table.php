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
        Schema::table('bir_2316', function (Blueprint $table) {
            $table->string('schedule_type')->nullable()->after('employee_id');
            $table->string('tin')->nullable()->after('schedule_type');
            $table->string('last_name')->nullable()->after('tin');
            $table->string('first_name')->nullable()->after('last_name');
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('registered_address')->nullable()->after('middle_name');
            $table->date('date_of_birth')->nullable()->after('registered_address');
            $table->string('contact_number')->nullable()->after('date_of_birth');

            $table->dropColumn([
                'others_taxable_a_label',
                'others_taxable_b_label',
                'others_51a_label',
                'others_51b_label',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bir_2316', function (Blueprint $table) {
            $table->dropColumn([
                'schedule_type',
                'tin',
                'last_name',
                'first_name',
                'middle_name',
                'registered_address',
                'date_of_birth',
                'contact_number',
            ]);

            $table->string('others_taxable_a_label')->nullable();
            $table->string('others_taxable_b_label')->nullable();
            $table->string('others_51a_label')->nullable();
            $table->string('others_51b_label')->nullable();
        });
    }
};
