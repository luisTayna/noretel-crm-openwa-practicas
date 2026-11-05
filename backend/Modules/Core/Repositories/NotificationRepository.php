<?php

namespace Modules\Core\Repositories;

use Modules\Core\Entities\Notification;
use Modules\Core\Entities\NotificationTemplate;

class NotificationRepository extends BaseRepository
{
    /**
     * @var NotificationTemplate
     */
    protected $templateModel;

    /**
     * NotificationRepository constructor.
     *
     * @param Notification $model
     * @param NotificationTemplate $templateModel
     */
    public function __construct(Notification $model, NotificationTemplate $templateModel)
    {
        parent::__construct($model);
        $this->templateModel = $templateModel;
    }

    /**
     * Get pending notifications
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPendingNotifications()
    {
        return Notification::getPendingNotifications();
    }

    /**
     * Create a notification from a template
     *
     * @param string $templateName
     * @param string $recipient
     * @param array $data
     * @param string|null $channel
     * @return Notification|null
     */
    public function createFromTemplate($templateName, $recipient, array $data, $channel = null)
    {
        $template = $this->templateModel
            ->where('name', $templateName)
            ->where('active', true)
            ->first();
            
        if (!$template) {
            return null;
        }
        
        return $template->createNotification($recipient, $data, $channel);
    }

    /**
     * Mark notification as sent
     *
     * @param int $id
     * @param array $metadata
     * @return Notification
     */
    public function markAsSent($id, array $metadata = [])
    {
        $notification = $this->find($id);
        return $notification->markAsSent($metadata);
    }

    /**
     * Mark notification as failed
     *
     * @param int $id
     * @param string $reason
     * @return Notification
     */
    public function markAsFailed($id, $reason)
    {
        $notification = $this->find($id);
        return $notification->markAsFailed($reason);
    }

    /**
     * Queue notification for sending
     *
     * @param int $id
     * @param \DateTime|null $sendAt
     * @return Notification
     */
    public function queueNotification($id, $sendAt = null)
    {
        $notification = $this->find($id);
        return $notification->queue($sendAt);
    }

    /**
     * Get notifications by recipient
     *
     * @param string $recipient
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getNotificationsByRecipient($recipient, $limit = 20)
    {
        return $this->model
            ->where('recipient', $recipient)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get templates by type
     *
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTemplatesByType($type)
    {
        return $this->templateModel
            ->where('communication_type', $type)
            ->where('active', true)
            ->get();
    }

    /**
     * Create or update a template
     *
     * @param array $data
     * @return NotificationTemplate
     */
    public function saveTemplate(array $data)
    {
        if (isset($data['id'])) {
            $template = $this->templateModel->findOrFail($data['id']);
            $template->update($data);
        } else {
            $template = $this->templateModel->create($data);
        }
        
        return $template;
    }
}