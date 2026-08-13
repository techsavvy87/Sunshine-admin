<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KennelBlock extends Model
{
    protected $fillable = [
        'kennel_id',
        'blocked_from',
        'blocked_to',
        'reason',
    ];

    protected $casts = [
        'blocked_from' => 'date',
        'blocked_to' => 'date',
    ];

    public function kennel()
    {
        return $this->belongsTo(Kennel::class);
    }

    /**
     * Scope to blocks whose [blocked_from, blocked_to] range overlaps the given [start, end] range.
     */
    public function scopeOverlapping($query, $start, $end)
    {
        return $query->whereDate('blocked_from', '<=', $end)
            ->whereDate('blocked_to', '>=', $start);
    }
}
