<?php

namespace App\Console\Commands;

use App\Models\Branch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class UpdateTerminalDevicesOfBranchSeeder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-terminal-devices-of-branch-seeder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command will update the biotime terminal device of the branch';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $user = env('BIOMETRICS_USERNAME');
        $password = env('BIOMETRICS_PASSWORD');
        $url = 'http://120.28.114.47:9090/iclock/api/terminals/';

        $response = Http::withBasicAuth($user, $password)
            ->withHeaders([
                "Accept" => "application/json",
                "Content-Type" => "application/json"
            ])
            ->timeout(60)
            ->get($url, [
                "page_size" => 10000000000
            ]);

        if ($response->successful()) {
            $resp = $response->json();
            $terminals = $resp["data"];
            foreach($terminals as $terminal) {
                $branch = Branch::where("short_name", $terminal["area_name"])->first();
                if ($branch && strtolower($branch->status) === "active") {
                    $branch->update([
                        "device_name" => $terminal["sn"]
                    ]);
                }
            }
        }
    }
}
