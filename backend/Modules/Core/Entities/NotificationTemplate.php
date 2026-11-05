<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class NotificationTemplate extends Model implements Auditable
{
    use HasFactory, AuditableTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'communication_type',
        'subject',
        'content',
        'variables',
        'active',
        'language'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'variables' => 'array',
        'active' => 'boolean'
    ];

    /**
     * Get the notifications created from this template.
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class, 'template_id');
    }

    /**
     * Compile the template content with the provided data.
     *
     * @param array $data
     * @return string
     */
    public function compile($data)
    {
        $content = $this->content;
        
        // Reemplazar variables en el contenido
        foreach ($data as $key => $value) {
            $content = str_replace('{' . $key . '}', $value, $content);
        }
        
        return $content;
    }

    /**
     * Compile the template subject with the provided data.
     *
     * @param array $data
     * @return string
     */
    public function compileSubject($data)
    {
        if (!$this->subject) {
            return '';
        }
        
        $subject = $this->subject;
        
        // Reemplazar variables en el asunto
        foreach ($data as $key => $value) {
            $subject = str_replace('{' . $key . '}', $value, $subject);
        }
        
        return $subject;
    }

    /**
     * Create a notification from this template.
     *
     * @param string $recipient
     * @param array $data
     * @param string $channel
     * @return Notification
     */
    public function createNotification($recipient, $data, $channel = null)
    {
        $channel = $channel ?: $this->communication_type;
        
        return Notification::create([
            'template_id' => $this->id,
            'type' => $this->name,
            'channel' => $channel,
            'recipient' => $recipient,
            'content' => $this->compile($data),
            'status' => 'pending',
            'metadata' => [
                'subject' => $this->compileSubject($data),
                'template_data' => $data
            ]
        ]);
    }
}