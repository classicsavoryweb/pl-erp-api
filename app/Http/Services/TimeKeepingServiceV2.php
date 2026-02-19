<?php

namespace App\Http\Services;

use App\Models\BiotimeLog;
use App\Models\BiotimePunch;
use App\Models\Cutoff;
use App\Models\Holiday;
use App\Models\Overtime;
use App\Models\ScheduleMasterlist;
use App\Models\ScheduleTime;
use App\Models\TimeKeeping;
use App\Models\TimeKeepingPayment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Carbon\CarbonInterface;
use Exception;
use Illuminate\Support\Facades\Http;

class TimeKeepingServiceV2
{
    public function computeTimeKeeping() {
        
    }
}