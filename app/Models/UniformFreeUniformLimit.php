<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UniformFreeUniformLimit extends Model
{
    use HasFactory;

    protected $fillable = [
        'description',
        'allowed_count',
        'status',
        'created_by',
        'updated_by',
        'date_from',
        'date_to'
    ];

    protected $casts = [
        'allowed_count' => 'integer',
    ];

    public static function currentAllowedCount(): int
    {
        return (int) (self::query()
            ->where('status', 'active')
            ->latest('id')
            ->value('allowed_count') ?? 1);
    }
}
