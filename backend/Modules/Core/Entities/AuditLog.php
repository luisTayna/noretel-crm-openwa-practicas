<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AuditLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'action_date',
        'action_type',
        'module',
        'action_detail',
        'source_ip',
        'previous_data',
        'new_data'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'action_date' => 'datetime',
        'previous_data' => 'array',
        'new_data' => 'array'
    ];

    /**
     * Get the user that performed the action.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Create a new audit log entry.
     *
     * @param int $userId
     * @param string $actionType
     * @param string $module
     * @param string $actionDetail
     * @param string $sourceIp
     * @param array|null $previousData
     * @param array|null $newData
     * @return AuditLog
     */
    public static function register($userId, $actionType, $module, $actionDetail, $sourceIp, $previousData = null, $newData = null)
    {
        return self::create([
            'user_id' => $userId,
            'action_date' => now(),
            'action_type' => $actionType,
            'module' => $module,
            'action_detail' => $actionDetail,
            'source_ip' => $sourceIp,
            'previous_data' => $previousData,
            'new_data' => $newData
        ]);
    }
}