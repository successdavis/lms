<?php

namespace App\Models;

use App\Enums\InstitutionType;
use Illuminate\Database\Eloquent\Model;

class Institution extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => InstitutionType::class,
            'settings' => 'array',
        ];
    }

    public static function current(): ?self
    {
        return static::query()->first();
    }
}
