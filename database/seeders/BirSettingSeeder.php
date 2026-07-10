<?php

namespace Database\Seeders;

use App\Models\BirSetting;
use Illuminate\Database\Seeder;

class BirSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        BirSetting::updateOrCreate(
            ['company_code' => 'SFI'],
            [
                'tin' => '006/727/988',
                'rdo_code' => '032',
                'name' => 'SAVORY FASTFOOD INC.',
                'address' => '#84 Sto Domingo St. Corner NS Amoranto  Brgy. Sto Domingo Quezon City',
                'zip' => '1104',
                'contact_number' => '4168860',
                'atc' => 'WW010',
                'is_government' => false,
                'is_amended_return' => false,
                'has_tax_relief' => false,
            ]
        );

        BirSetting::updateOrCreate(
            ['company_code' => 'TTFC'],
            [
                'tin' => '008/726/556',
                'rdo_code' => '032',
                'name' => 'Tony Ting Food Concepts Incorporated',
                'address' => 'Unit 301 TP Building 414 Pureza Street Brgy. 425 Sampaloc Manila',
                'zip' => '1008',
                'contact_number' => '70046704',
                'atc' => 'WW010',
                'is_government' => false,
                'is_amended_return' => false,
                'has_tax_relief' => false,
            ]
        );
    }
}
