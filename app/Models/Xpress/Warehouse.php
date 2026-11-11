<?php

namespace App\Models\Xpress;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\DeletedModels\Models\Concerns\KeepsDeletedModels;

class Warehouse extends Model
{
    use HasFactory, KeepsDeletedModels, LogsActivity;

    protected $fillable = [
        'user_id',
        'warehouse_code',
        'warehouse_name',
        'active_status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('warehouse')->logOnlyDirty();
    }
}
