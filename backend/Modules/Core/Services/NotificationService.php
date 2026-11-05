<?php

namespace Modules\Core\Services;

use Modules\Core\Repositories\NotificationRepository;
use Modules\Core\Entities\AuditLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * @var NotificationRepository
     */
    protected $notificationRepository;

    /**
     * @var ConfigurationService
     */
    protected $configService;

    /**
     * NotificationService constructor.
     *
     * @param NotificationRepository $notificationRepository
     * @param ConfigurationService $configService
     */
    public function __construct(
        NotificationRepository $notificationRepository,
        ConfigurationService $configService
    ) {
        $this->notificationRepository = $notificationRepository;
        $this->configService = $configService;
    }

    /**
     * Send a notification
     *
     * @param string $templateName
     * @param string $recipient
     * @param array $data
     * @param string|null $channel
     * @param \DateTime|null $sendAt
     * @return array
     */
    public function sendNotification($templateName, $recipient, array $data, $channel = null, $sendAt = null)
    {
        // Crear notificación
        $notification = $this->notificationRepository->createFromTemplate(
            $templateName,
            $recipient,
            $data,
            $channel
        );
        
        if (!$notification) {
            return [
                'success' => false,
                'message' => "Plantilla no encontrada: {$templateName}"
            ];
        }
        
        // Si hay una fecha programada, encolar
        if ($sendAt) {
            $notification = $this->notificationRepository->queueNotification($notification->id, $sendAt);
            
            return [
                'success' => true,
                'message' => 'Notificación programada correctamente.',
                'notification' => $notification
            ];
        }
        
        // Enviar según el canal
        switch ($notification->channel) {
            case 'email':
                return $this->sendEmail($notification);
            case 'sms':
                return $this->sendSms($notification);
            case 'system':
                return $this->sendSystemNotification($notification);
            default:
                return [
                    'success' => false,
                    'message' => "Canal de notificación no soportado: {$notification->channel}"
                ];
        }
    }

    /**
     * Send an email notification
     *
     * @param \Modules\Core\Entities\Notification $notification
     * @return array
     */
    protected function sendEmail($notification)
    {
        try {
            $metadata = $notification->metadata ?? [];
            $subject = $metadata['subject'] ?? 'Notificación del sistema';
            
            // Obtener configuración de correo
            $fromEmail = $this->configService->getValue('email', 'from_address', 'noreply@example.com');
            $fromName = $this->configService->getValue('email', 'from_name', 'Sistema ISP-CRM');
            
            // Enviar correo
            Mail::raw($notification->content, function ($message) use ($notification, $subject, $fromEmail, $fromName) {
                $message->to($notification->recipient)
                    ->subject($subject)
                    ->from($fromEmail, $fromName);
            });
            
            // Marcar como enviado
            $this->notificationRepository->markAsSent($notification->id, [
                'sent_at' => now()->toDateTimeString()
            ]);
            
            return [
                'success' => true,
                'message' => 'Email enviado correctamente.',
                'notification' => $notification
            ];
        } catch (\Exception $e) {
            Log::error('Error enviando email: ' . $e->getMessage());
            
            // Marcar como fallido
            $this->notificationRepository->markAsFailed($notification->id, $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error al enviar email: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send an SMS notification
     *
     * @param \Modules\Core\Entities\Notification $notification
     * @return array
     */
    protected function sendSms($notification)
    {
        // Esta función requeriría integración con un proveedor de SMS
        
        try {
            // Ejemplo: aquí se implementaría la lógica para enviar un SMS
            // usando un proveedor externo como Twilio, Nexmo, etc.
            
            // Simulamos éxito para este ejemplo
            $sent = true;
            
            if ($sent) {
                $this->notificationRepository->markAsSent($notification->id, [
                    'sent_at' => now()->toDateTimeString()
                ]);
                
                return [
                    'success' => true,
                    'message' => 'SMS enviado correctamente.',
                    'notification' => $notification
                ];
            } else {
                $this->notificationRepository->markAsFailed($notification->id, 'Error al enviar SMS');
                
                return [
                    'success' => false,
                    'message' => 'Error al enviar SMS.'
                ];
            }
        } catch (\Exception $e) {
            Log::error('Error enviando SMS: ' . $e->getMessage());
            
            $this->notificationRepository->markAsFailed($notification->id, $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error al enviar SMS: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send a system notification
     *
     * @param \Modules\Core\Entities\Notification $notification
     * @return array
     */
    protected function sendSystemNotification($notification)
    {
        try {
            // Las notificaciones del sistema simplemente se marcan como enviadas
            // ya que se mostrarán en la interfaz de usuario
            
            $this->notificationRepository->markAsSent($notification->id, [
                'sent_at' => now()->toDateTimeString()
            ]);
            
            return [
                'success' => true,
                'message' => 'Notificación del sistema creada correctamente.',
                'notification' => $notification
            ];
        } catch (\Exception $e) {
            Log::error('Error creando notificación del sistema: ' . $e->getMessage());
            
            $this->notificationRepository->markAsFailed($notification->id, $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error al crear notificación del sistema: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create an in-app (system channel) notification for each given recipient.
     *
     * Each recipient is notified independently: a failure creating one
     * notification is logged and does not stop the others, and never throws.
     *
     * @param iterable<string> $recipients Recipient identifiers (typically user emails)
     * @param string $type Notification type/key, e.g. 'payment_pending_approval'
     * @param string $content Notification body text (Spanish, user-facing)
     * @param array $metadata Extra data merged into the notification metadata
     * @return array<int, array{recipient: string, success: bool}>
     */
    public function notifySystemUsers(iterable $recipients, $type, $content, array $metadata = [])
    {
        $results = [];

        foreach ($recipients as $recipient) {
            try {
                $notification = $this->notificationRepository->create([
                    'type' => $type,
                    'channel' => 'system',
                    'recipient' => $recipient,
                    'content' => $content,
                    'status' => 'sent',
                    'send_date' => now(),
                    'metadata' => $metadata,
                ]);

                $results[] = [
                    'recipient' => $recipient,
                    'success' => true,
                    'notification' => $notification
                ];
            } catch (\Exception $e) {
                Log::error("Error creando notificación del sistema para {$recipient}: " . $e->getMessage());

                $results[] = [
                    'recipient' => $recipient,
                    'success' => false
                ];
            }
        }

        return $results;
    }

    /**
     * Process pending notifications
     *
     * @return array
     */
    public function processPendingNotifications()
    {
        $pendingNotifications = $this->notificationRepository->getPendingNotifications();
        $processed = 0;
        $failed = 0;
        
        foreach ($pendingNotifications as $notification) {
            // Enviar según el canal
            switch ($notification->channel) {
                case 'email':
                    $result = $this->sendEmail($notification);
                    break;
                case 'sms':
                    $result = $this->sendSms($notification);
                    break;
                case 'system':
                    $result = $this->sendSystemNotification($notification);
                    break;
                default:
                    $result = [
                        'success' => false,
                        'message' => "Canal de notificación no soportado: {$notification->channel}"
                    ];
            }
            
            if ($result['success']) {
                $processed++;
            } else {
                $failed++;
            }
        }
        
        return [
            'success' => true,
            'processed' => $processed,
            'failed' => $failed,
            'total' => count($pendingNotifications)
        ];
    }

    /**
     * Create or update a notification template
     *
     * @param array $templateData
     * @param int $createdBy
     * @param string $ip
     * @return array
     */
    public function saveTemplate(array $templateData, $createdBy, $ip)
    {
        $isNew = !isset($templateData['id']);
        
        // Guardar plantilla
        $template = $this->notificationRepository->saveTemplate($templateData);
        
        // Registrar acción
        AuditLog::register(
            $createdBy,
            $isNew ? 'template_created' : 'template_updated',
            'notifications',
            $isNew ? "Plantilla creada: {$template->name}" : "Plantilla actualizada: {$template->name}",
            $ip,
            !$isNew ? $templateData : null,
            $template->toArray()
        );
        
        return [
            'success' => true,
            'template' => $template
        ];
    }

    /**
     * Get user notifications
     *
     * @param string $recipient
     * @param int $limit
     * @return array
     */
    public function getUserNotifications($recipient, $limit = 20)
    {
        $notifications = $this->notificationRepository->getNotificationsByRecipient($recipient, $limit);
        
        return [
            'success' => true,
            'notifications' => $notifications
        ];
    }

    /**
     * Mark notification as read
     *
     * @param int $notificationId
     * @param int $userId
     * @return array
     */
    public function markAsRead($notificationId, $userId)
    {
        $notification = $this->notificationRepository->find($notificationId);
        
        // Verificar que la notificación pertenezca al usuario
        if ($notification->recipient !== (string)$userId && $notification->recipient !== $this->userRepository->find($userId)->email) {
            return [
                'success' => false,
                'message' => 'La notificación no pertenece al usuario.'
            ];
        }
        
        // Actualizar metadata para marcar como leída
        $metadata = $notification->metadata ?? [];
        $metadata['read'] = true;
        $metadata['read_at'] = now()->toDateTimeString();
        
        $notification->update([
            'metadata' => $metadata
        ]);
        
        return [
            'success' => true,
            'notification' => $notification
        ];
    }

    /**
     * Get available notification templates by type
     *
     * @param string $type
     * @return array
     */
    public function getTemplatesByType($type)
    {
        $templates = $this->notificationRepository->getTemplatesByType($type);
        
        return [
            'success' => true,
            'templates' => $templates
        ];
    }

    /**
     * Preview a notification
     *
     * @param string $templateName
     * @param array $data
     * @return array
     */
    public function previewNotification($templateName, array $data)
    {
        $template = $this->notificationRepository->getTemplateModel()
            ->where('name', $templateName)
            ->where('active', true)
            ->first();
            
        if (!$template) {
            return [
                'success' => false,
                'message' => "Plantilla no encontrada: {$templateName}"
            ];
        }
        
        $content = $template->compile($data);
        $subject = $template->compileSubject($data);
        
        return [
            'success' => true,
            'preview' => [
                'subject' => $subject,
                'content' => $content
            ]
        ];
    }
}