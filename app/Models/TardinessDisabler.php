<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class TardinessDisabler extends Model
{
    protected $fillable = [
        'month',
        'year',
        'is_disabled',
    ];

    protected $casts = [
        'is_disabled' => 'boolean',
    ];

    public static function isDisabled(int $month, int $year): bool
    {
        return self::where('month', $month)
            ->where('year', $year)
            ->where('is_disabled', true)
            ->exists();
    }

    public static function upsertDisabledForMonth(Carbon $date): void
    {
        self::updateOrCreate(
            [
                'month' => (int) $date->month,
                'year' => (int) $date->year,
            ],
            [
                'is_disabled' => true,
            ]
        );
    }
}
