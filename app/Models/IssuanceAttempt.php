<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssuanceAttempt extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'http_status' => 'integer',
        ];
    }

    public function policyRequest(): BelongsTo
    {
        return $this->belongsTo(PolicyRequest::class);
    }
}
