<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

class FetchOvertimeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userId;
    protected $notificationContext;

    public function __construct(?int $userId = null, ?string $notificationContext = null)
    {
        $this->userId = $userId;
        $this->notificationContext = $notificationContext;
    }

    public function handle(): void
    {
        Artisan::call("app:fetch-overtime-in-cs-hub");
    }
}
