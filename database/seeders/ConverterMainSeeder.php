<?php

namespace Database\Seeders;

use App\Models\ConverterMain;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ConverterMainSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/payroll/converter_main.json');
        $jsonData = file_get_contents($jsonPath);
        $details = json_decode($jsonData, true);

        foreach($details["tbl_converter_main"] as $detail) {
                // {
                // "id": "1",
                // "batch_no": "CON-0000001",
                // "total_amount": "15000.00",
                // "count": "1",
                // "status": "Voided",
                // "entry_status": " ",
                // "email_status": " ",
                // "remarks": "",
                // "added_by": "Clarita Cruz",
                // "added_date": "2025-04-02 11:58:04",
                // "modified_by": null,
                // "modified_date": null,
                // "posted_by": null,
                // "posted_date": null,
                // "voided_by": "Clarita Cruz",
                // "voided_date": "2025-04-02 12:24:11",
                // "generated_by": null,
                // "generated_date": null,
                // "card_type": "5",
                // "transtype_filter": "4",
                // "emp_filter": null
                // },
            
            ConverterMain::create([
                "batch_number"  => $detail["batch_no"],
                "total_amount" => $detail["total_amount"],
                "count" => $detail["count"],
                "status" => $detail["status"],
                "remarks" => $detail["remarks"],
                "bank_account_type_id" => $detail["card_type"] == 5 ? 1 : 2,
                "transaction_type_id" => $detail["transtype_filter"],
                "created_at" => $detail["added_date"],
                "generated_at" => $detail["generated_date"],
                "posted_at" => $detail["posted_date"],
                "voided_at" => $detail["voided_date"],
            ]);
        }
    }
}
