<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeType extends Model
{
    use Auditable;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'blocks_registration' => 'boolean',
            'is_recurring' => 'boolean',
        ];
    }

    public function structures(): HasMany
    {
        return $this->hasMany(FeeStructure::class);
    }
}
