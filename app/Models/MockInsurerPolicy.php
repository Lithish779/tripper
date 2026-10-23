<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MockInsurerPolicy extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'premium' => 'float',
            'details' => 'array',
            'issued_at' => 'datetime',
        ];
    }
}
