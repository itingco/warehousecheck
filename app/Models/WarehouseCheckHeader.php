<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseCheckHeader extends Model
{
    protected $connection = 'apphub';
    protected $table = 'warehouse_check_headers';

    protected $fillable = [
        'company',
        'source_database',
        'invoice_id',
        'invoice_number',
        'invoice_date',
        'customer_name',
        'shipping_address',
        'picker_user_id',
        'checker_user_id',
        'status',
        'started_at',
        'completed_at',
        'source_updated_at',
    ];

    protected $casts = [
        'invoice_date' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'source_updated_at' => 'datetime',
    ];

    public function details(): HasMany
    {
        return $this->hasMany(WarehouseCheckDetail::class, 'check_header_id');
    }

    public function picker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'picker_user_id');
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checker_user_id');
    }

    public function scanErrors(): HasMany
    {
        return $this->hasMany(WarehouseCheckScanError::class, 'check_header_id');
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(WarehouseCheckOverride::class, 'check_header_id');
    }
}
