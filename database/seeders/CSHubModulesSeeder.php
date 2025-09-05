<?php

namespace Database\Seeders;

use App\Models\CSHubModule;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CSHubModulesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/payroll/cs_hub_modules.json');
        $jsonData = file_get_contents($jsonPath);
        $modules = json_decode($jsonData, true);

        foreach($modules["cshub_modules"] as $module) {
            
            $status = ucfirst($module["status"]);
                if ($status === 'Active') {
                    $status = 'active';
                } else {
                    $status = 'inactive';
                }

            CSHubModule::create([
                "description" => $module["description"],
                "status" => $status,
            ]);
        }
    }
}
