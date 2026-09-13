<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupervisorOtp extends Model
{
    protected $connection = 'apphub';
    protected $table = 'supervisor_otps';

    protected $fillable = [
        'supervisor_user_id',
        'code_hash',
        'code_encrypted',
        'generated_at',
        'expires_at',
        'revoked_at',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function isActive(): bool
    {
        return !$this->revoked_at
            && $this->expires_at
            && $this->expires_at->isFuture();
    }
}
