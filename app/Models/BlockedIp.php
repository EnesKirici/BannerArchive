<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlockedIp extends Model
{
    protected $fillable = [
        'ip_address',
        'reason',
        'blocked_until',
    ];

    protected function casts(): array
    {
        return [
            'blocked_until' => 'datetime',
        ];
    }

    /**
     * @param string $ip
     */
    public static function isBlocked(string $ip): bool
    {
        return static::where('ip_address', $ip)
            ->where(function ($query) {
                $query->whereNull('blocked_until')
                    ->orWhere('blocked_until', '>', now());
            })
            ->exists();
    }
}
