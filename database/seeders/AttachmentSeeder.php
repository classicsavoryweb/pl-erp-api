<?php

namespace Database\Seeders;

use App\Models\ActingCapacity;
use App\Models\ActingCapacityAttachments;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AttachmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/attachments.json');
        $jsonData = file_get_contents($jsonPath);
        $attachments = json_decode($jsonData, true);

        foreach($attachments["tbl_attachments"] as $attach) {

            $acting = ActingCapacity::find($attach["acting_id"]);

            ActingCapacityAttachments::create([
                "acting_capacity_id" => $acting->id,
                "file_path" => $attach["img_path"],
                "file_name" => $attach["attachmenttype"],
            ]);
        }
    }
}
