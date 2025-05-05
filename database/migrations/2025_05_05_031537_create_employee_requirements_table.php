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
        Schema::create('employee_requirements', function (Blueprint $table) {
            $table->id();
            $table->string("employee_id");
            $table->string("employee_number");
            $table->string("birth_certificate")->default("no");
            $table->string("marriage_certificate")->default("no");
            $table->string("birth_certificate_dependents")->default("no");
            $table->string("sss")->default("no");
            $table->string("check_1902")->default("no");
            $table->string("check_1905")->default("no");
            $table->string("philhealth")->default("no");
            $table->string("pagibig")->default("no");
            $table->string("tin")->default("no");
            $table->string("picture2x2")->default("no");
            $table->string("check_diploma")->default("no");
            $table->string("tor")->default("no");
            $table->string("cot")->default("no");
            $table->string("coe")->default("no");
            $table->string("nbi")->default("no");
            $table->string("drug_test")->default("no");
            $table->string("clinical_microscopy")->default("no");
            $table->string("chest_xray")->default("no");
            $table->string("occupational_permit")->default("no");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_requirements');
    }
};
