<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseCheckOverride extends Model
{
    protected $connection = 'apphub';
    protected $table = 'warehouse_check_overrides';

    protected $fillable = [
        'check_header_id',
        'check_detail_id',
        'supervisor_user_id',
        'checker_user_id',
        'otp_id',
        'reason_code',
        'reason_note',
        'overridden_at',
    ];

    protected $casts = [
        'overridden_at' => 'datetime',
    ];
}
