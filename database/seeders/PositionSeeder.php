<?php

namespace Database\Seeders;

use App\Models\Position;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PositionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $jsonPath = public_path('jsons/position.json');
        $jsonData = file_get_contents($jsonPath);
        $pos = json_decode($jsonData, true);

        $positions = array_map("unserialize", array_unique(array_map("serialize", $pos["tbl_position"])));
        foreach($positions as $position) {
            $schedule = "Others";
            if ( strtolower($position["station"]) === "head office") {
                if (in_array($position["position_descr"], ['Stockman', 'Technician', 'Driver', 'Repair & Maintenance Staff'])) {
                    $schedule = "Normal Schedule";
                } else {
                    $schedule = "Compressed Schedule";
                }
            } else {
                $schedule = "Normal Schedule";
            }

            if ($position["status"] === "inactive") {
              $schedule = "N/A";
            }

            if (in_array($position["position_descr"], ['Ala Carte Cook', 'All Around Cook', 'Assisstant Head Cook Trainee', 'Cashier', 'Chicken Cook', 'Cook Trainee', 'Food Preparation Staff', 'Part-Time Cashier', 'Part-Time Team Member', 'Senior Cashier', 'Team Leader', 'Team Member', 'Driver', 'Stockman', 'Technician', 'Repair & Maintenance Staff', 'Janitor/Maintenance Staff', 'Production Team Leader'])) {
                $timekeeping = "Daily";
            } else {
                $timekeeping = "Monthly";
            }

            Position::create([
                "description" => $position["position_descr"],
                "rank" => $position["rank"],
                "status" => $position["status"],
                "station_id" => $position["station_id"],
                "schedule_type" => $schedule,
                "timekeeping_type"=> $timekeeping,
            ]);
        };
    }
}
