<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StructureType extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'sort_order'];

    public function structures(): HasMany
    {
        return $this->hasMany(Structure::class);
    }
}
