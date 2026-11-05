<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Notification extends Model implements Auditable
{
    use HasFactory, AuditableTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'template_id',
        'type',
        'channel',
        'recipient',
        'content',
        'send_date',
        'status',
        'metadata'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'send_date' => 'datetime',
        'metadata' => 'array'
    ];

    /**
     * Get the template that was used to create this notification.
     */
    public function template()
    {
        return $this->belongsTo(NotificationTemplate::class, 'template_id');
    }

    /**
     * Mark the notification as sent.
     *
     * @param array $metadata
     * @return self
     */
    public function markAsSent($metadata = [])
    {
        $this->update([
            'status' => 'sent',
            'send_date' => now(),
            'metadata' => array_merge($this->metadata ?? [], $metadata)
        ]);

        return $this;
    }

    /**
     * Mark the notification as failed.
     *
     * @param string $reason
     * @return self
     */
    public function markAsFailed($reason)
    {
        $this->update([
            'status' => 'failed',
            'metadata' => array_merge($this->metadata ?? [], ['error' => $reason])
        ]);

        return $this;
    }

    /**
     * Queue the notification for sending.
     *
     * @param \DateTime|null $sendAt
     * @return self
     */
    public function queue($sendAt = null)
    {
        $this->update([
            'status' => 'queued',
            'send_date' => $sendAt
        ]);

        return $this;
    }

    /**
     * Get pending notifications ready to be sent.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getPendingNotifications()
    {
        return static::where('status', 'queued')
            ->where(function ($query) {
                $query->whereNull('send_date')
                    ->orWhere('send_date', '<=', now());
            })
            ->get();
    }
}