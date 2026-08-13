<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kennel extends Model
{
    protected $fillable = [
        'img',
        'name',
        'description',
        'type',
        'capacity',
        'status',
    ];

    public function blocks()
    {
        return $this->hasMany(KennelBlock::class);
    }

    /**
     * Returns the block overlapping the given date range, if any.
     * Dates should be Y-m-d strings or Carbon-compatible values.
     */
    public function overlappingBlock($start, $end)
    {
        return $this->blocks()->overlapping($start, $end)->orderBy('blocked_from')->first();
    }
}
