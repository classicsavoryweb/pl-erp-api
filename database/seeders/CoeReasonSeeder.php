<?php

namespace Database\Seeders;

use App\Models\CoeReason;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CoeReasonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CoeReason::create(["reason" => "Cellphone Plan Application" ,"status" => "active"]);
        CoeReason::create(["reason" => "Motorcycle Loan Application","status" => "active"]);
        CoeReason::create(["reason" => "Housing Loan Application","status" => "active"]);
        CoeReason::create(["reason" => "Travel Purposes only","status" => "active"]);
        CoeReason::create(["reason" => "PAG-IBIG Housing Loan Application","status" => "active"]);
        CoeReason::create(["reason" => "Pollution Control Officer","status" => "active"]);
        CoeReason::create(["reason" => "Solo Parent ID Application","status" => "active"]);
        CoeReason::create(["reason" => "Cooperative Loan Application","status" => "active"]);
        CoeReason::create(["reason" => "Car Loan Application","status" => "active"]);
        CoeReason::create(["reason" => "Laptop Loan Application","status" => "active"]);
    }
}
