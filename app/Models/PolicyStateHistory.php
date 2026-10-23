<?php

namespace App\Models;

use App\Enums\PolicyStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PolicyStateHistory extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'from_status' => PolicyStatus::class,
            'to_status' => PolicyStatus::class,
            'metadata' => 'array',
        ];
    }

    public function policyRequest(): BelongsTo
    {
        return $this->belongsTo(PolicyRequest::class);
    }
}
