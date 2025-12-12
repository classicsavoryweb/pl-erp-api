<?php

namespace Database\Seeders;

use App\Models\Rate;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rates = [
            [
              "auto_id"=> "1",
              "rate_code"=> "A",
              "status"=> "active",
              "minimum"=> "600.00",
              "area"=> "III 10/17/2024",
              "added_by"=> "Administrator",
              "added_date"=> "2023-09-02 00=>00=>00",
              "modify_by"=> "Clarita Cruz",
              "modify_date"=> "2025-04-16 00=>00=>00"
            ],
            [
              "auto_id"=> "2",
              "rate_code"=> "B",
              "status"=> "inactive",
              "minimum"=> "0.00",
              "area"=> "",
              "added_by"=> "Administrator",
              "added_date"=> "2023-09-02 00=>00=>00",
              "modify_by"=> "Clarita Cruz",
              "modify_date"=> "2025-01-29 00=>00=>00"
            ],
            [
              "auto_id"=> "3",
              "rate_code"=> "C",
              "status"=> "active",
              "minimum"=> "540.00",
              "area"=> " XI",
              "added_by"=> "Administrator",
              "added_date"=> "2023-09-02 00=>00=>00",
              "modify_by"=> "Clarita Cruz",
              "modify_date"=> "2024-09-27 00=>00=>00"
            ],
            [
              "auto_id"=> "4",
              "rate_code"=> "D",
              "status"=> "active",
              "minimum"=> "445.00",
              "area"=> "V ( DAE, LGP, SOR, NAG )4/5/2025-445 12/31/2025 - 465",
              "added_by"=> "Administrator",
              "added_date"=> "2023-09-02 00=>00=>00",
              "modify_by"=> "Clarita Cruz",
              "modify_date"=> "2025-04-21 00=>00=>00"
            ],
            [
              "auto_id"=> "5",
              "rate_code"=> "E",
              "status"=> "active",
              "minimum"=> "695.00",
              "area"=> "NCR, Special Rate (III & IVA)",
              "added_by"=> "Administrator",
              "added_date"=> "2023-09-02 00=>00=>00",
              "modify_by"=> "Clarita Cruz",
              "modify_date"=> "2024-07-19 00=>00=>00"
            ],
            [
              "auto_id"=> "6",
              "rate_code"=> "F",
              "status"=> "active",
              "minimum"=> "620.00",
              "area"=> " IVA (1) DSM, RSP, CSP, IMU, SIM, DSM, BCR, MOL EFFECTIVE 9/30/2024",
              "added_by"=> "Administrator",
              "added_date"=> "2023-09-25 00=>00=>00",
              "modify_by"=> "Clarita Cruz",
              "modify_date"=> "2024-10-07 00=>00=>00"
            ],
            [
              "auto_id"=> "7",
              "rate_code"=> "G",
              "status"=> "inactive",
              "minimum"=> "0.00",
              "area"=> "",
              "added_by"=> "Administrator",
              "added_date"=> "2023-09-25 00=>00=>00",
              "modify_by"=> "Clarita Cruz",
              "modify_date"=> "2025-04-21 00=>00=>00"
            ],
            [
              "auto_id"=> "8",
              "rate_code"=> "H",
              "status"=> "active",
              "minimum"=> "580.00",
              "area"=> "VI",
              "added_by"=> "Administrator",
              "added_date"=> "2023-09-25 00=>00=>00",
              "modify_by"=> "Clarita Cruz",
              "modify_date"=> "2024-11-22 00=>00=>00"
            ],
            [
              "auto_id"=> "9",
              "rate_code"=> "I",
              "status"=> "active",
              "minimum"=> "560.00",
              "area"=> "VII (SCE, CSN,SEA) Effective 10/2/2024 ",
              "added_by"=> "Administrator",
              "added_date"=> "2023-09-25 00=>00=>00",
              "modify_by"=> "Clarita Cruz",
              "modify_date"=> "2024-10-07 00=>00=>00"
            ],
            [
              "auto_id"=> "10",
              "rate_code"=> "J",
              "status"=> "inactive",
              "minimum"=> "550.00",
              "area"=> "REGION IVA (2) LIP, BTG, SST, CAL, SER, LUC, PAV, SOL, SPA, SRS, TAN, WSR, TMS, WCM, RSR, TZA EFFECTIVE 4/1/2025",
              "added_by"=> "Clarita Cruz",
              "added_date"=> "2023-10-04 00=>00=>00",
              "modify_by"=> "Clarita Cruz",
              "modify_date"=> "2025-04-15 00=>00=>00"
            ],
            [
              "auto_id"=> "11",
              "rate_code"=> "K",
              "status"=> "active",
              "minimum"=> "535.00",
              "area"=> "REGION I  / CAR / CDO2 11/7/2024 CAR 12/24/2024 CDO2 1/12/2025",
              "added_by"=> "Clarita Cruz",
              "added_date"=> "2024-11-22 00=>00=>00",
              "modify_by"=> "Clarita Cruz",
              "modify_date"=> "2025-01-29 00=>00=>00"
            ],
            [
              "auto_id"=> "12",
              "rate_code"=> "L",
              "status"=> "active",
              "minimum"=> "460.00",
              "area"=> "Region IVB 12/23/2024",
              "added_by"=> "Clarita Cruz",
              "added_date"=> "2025-01-13 00=>00=>00",
              "modify_by"=> "",
              "modify_date"=> "1900-01-01 00=>00=>00"
            ]
        ];

        foreach($rates as $rate){
            $rate['area'] = str_replace([" ", "\\n", "\\r"], PHP_EOL, $rate['area']);

            Rate::create([
                "code" => $rate["rate_code"],
                "minimum" => $rate["minimum"],
                "area" => $rate["area"],
                "status" => $rate["status"],
            ]);
        }
          
    }
}
