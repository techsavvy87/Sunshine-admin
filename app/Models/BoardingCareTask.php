<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BoardingCareTask extends Model
{
    protected $guarded = [];

    protected $casts = [
        'task_date' => 'date',
        'completed_at' => 'datetime',
    ];
}
