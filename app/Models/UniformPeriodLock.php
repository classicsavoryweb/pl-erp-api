<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class UniformPeriodLock extends Model
{
    public const TYPE_ISSUANCE = 'Issuance';
    public const TYPE_RECEIVING = 'Receiving';
    public const TYPE_ACKNOWLEDGEMENT = 'Acknowledgement';

    protected $fillable = [
        'period_name',
        'lock_type',
        'date_from',
        'date_to',
        'status',
        'prepared_by',
        'updated_by',
    ];

    protected $casts = [
        'date_from' => 'date:Y-m-d',
        'date_to' => 'date:Y-m-d',
    ];

    public static function ensureDateIsOpen(mixed $date, string $lockType = self::TYPE_ISSUANCE): void
    {
        $targetDate = date('Y-m-d', strtotime((string) $date));

        $isOpen = static::where('status', 'active')
            ->where('lock_type', $lockType)
            ->whereDate('date_from', '<=', $targetDate)
            ->whereDate('date_to', '>=', $targetDate)
            ->exists();

        if (!$isOpen) {
            throw ValidationException::withMessages([
                'period' => "Uniform {$lockType} period is inactive or not defined for {$targetDate}.",
            ]);
        }
    }
}
