<?php

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    public function run(): void
    {
        $regions = [
            ['name' => 'REGION I', 'description' => 'REGION I - Ilocos Region'],
            ['name' => 'REGION II', 'description' => 'REGION II - Cagayan Valley'],
            ['name' => 'REGION III', 'description' => 'REGION III - Central Luzon'],
            ['name' => 'REGION IVA', 'description' => 'REGION IV-A - CALABARZON'],
            ['name' => 'REGION IVB', 'description' => 'REGION IV-B - MIMAROPA'],
            ['name' => 'REGION V', 'description' => 'REGION V - Bicol Region'],
            ['name' => 'REGION VI', 'description' => 'REGION VI - Western Visayas'],
            ['name' => 'REGION VII', 'description' => 'REGION VII - Central Visayas'],
            ['name' => 'REGION VIII', 'description' => 'REGION VIII - Eastern Visayas'],
            ['name' => 'REGION IX', 'description' => 'REGION IX - Zamboanga Peninsula'],
            ['name' => 'REGION X', 'description' => 'REGION X - Northern Mindanao'],
            ['name' => 'REGION XI', 'description' => 'REGION XI - Davao Region'],
            ['name' => 'REGION XII', 'description' => 'REGION XII - Soccsksargen'],
            ['name' => 'REGION XIII', 'description' => 'REGION XIII - CARAGA'],
            ['name' => 'CAR', 'description' => 'REGION XIV - Cordillera Administrative Region (CAR)'],
            ['name' => 'REGION XV', 'description' => 'REGION XV - Autonomous Region in Muslim Mindanao (ARMM)'],
            ['name' => 'REGION XVIII', 'description' => 'REGION XVIII - Negros Island Region (NIR)'],
            ['name' => 'NCR', 'description' => 'NCR - National Capital Region'],
        ];

        foreach ($regions as $region) {
            Region::updateOrCreate(
                ['name' => $region['name']],
                [
                    'description' => $region['description'],
                    'status' => 'active',
                ]
            );
        }
    }
}
