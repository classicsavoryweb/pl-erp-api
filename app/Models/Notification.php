<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'message',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function createForUser(?int $userId, string $message): ?self
    {
        if (!$userId || $message === '') {
            return null;
        }

        return self::create([
            'user_id' => $userId,
            'message' => $message,
            'is_read' => 0,
        ]);
    }
}
