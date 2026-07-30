<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sighting extends Model
{
    protected $fillable = [
        'uid',
        'code',
        'car',
        'variant',
        'x',
        'y',
        'rotation',
        'scale',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'car' => 'integer',
            'variant' => 'integer',
            'x' => 'integer',
            'y' => 'integer',
            'rotation' => 'integer',
            'scale' => 'integer',
        ];
    }
}
