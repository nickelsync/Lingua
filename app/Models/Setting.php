<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['key', 'value'])]
class Setting extends Model
{
    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::remember('settings.all', 300, fn () => static::pluck('value', 'key')->all());

        return $all[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        Cache::forget('settings.all');
    }
}
