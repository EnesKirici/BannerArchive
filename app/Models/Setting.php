<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'group'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** @var list<string> */
    private static array $sensitiveKeys = [
        'tmdb_api_key',
        'gemini_api_key',
    ];

    /**
     * Get setting value with proper type casting
     */
    public function getTypedValueAttribute(): mixed
    {
        $value = $this->value;

        if (in_array($this->key, self::$sensitiveKeys) && $value) {
            try {
                $value = Crypt::decryptString($value);
            } catch (\Illuminate\Contracts\Encryption\DecryptException) {
                // Henüz şifrelenmemiş eski veri — olduğu gibi döndür
            }
        }

        return match ($this->type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Get a setting by key
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        return $setting ? $setting->typed_value : $default;
    }

    /**
     * Set a setting value
     */
    public static function set(string $key, mixed $value, string $type = 'string', string $group = 'general'): static
    {
        if ($type === 'json' && is_array($value)) {
            $value = json_encode($value);
        }

        if (in_array($key, self::$sensitiveKeys) && $value !== '' && $value !== null) {
            $value = Crypt::encryptString((string) $value);
        }

        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type, 'group' => $group]
        );
    }

    /**
     * Scope by group
     */
    public function scopeGroup(\Illuminate\Database\Eloquent\Builder $query, string $group): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('group', $group);
    }
}
