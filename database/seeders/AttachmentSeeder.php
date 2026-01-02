<?php

namespace Database\Seeders;

use App\Models\ActingCapacity;
use App\Models\ActingCapacityAttachments;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use function Laravel\Prompts\search;

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

            $imgPath = preg_replace('/\\\\+/', '\\', $attach['img_path']);
            $filepath = '\\\\' . ltrim($imgPath, '\\');

            ActingCapacityAttachments::create([
                "acting_capacity_id" => $acting->id,
                "file_path" => $filepath,
                "file_name" => $attach["attachmenttype"],
            ]);
        }
    }
}
