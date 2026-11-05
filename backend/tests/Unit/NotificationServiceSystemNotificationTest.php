<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Entities\Notification;
use Modules\Core\Services\NotificationService;
use Tests\TestCase;

/**
 * Verifies NotificationService::notifySystemUsers() creates one in-app
 * (channel "system") notification per recipient and never throws, even
 * when persisting a notification for one recipient fails.
 */
class NotificationServiceSystemNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_one_system_notification_per_recipient(): void
    {
        $service = app(NotificationService::class);

        $results = $service->notifySystemUsers(
            ['admin1@test.com', 'admin2@test.com'],
            'payment_pending_approval',
            'El cliente Juan Perez subio un comprobante de pago pendiente de aprobacion.',
            ['payment_id' => 42]
        );

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]['success']);
        $this->assertTrue($results[1]['success']);

        $this->assertDatabaseHas('notifications', [
            'recipient' => 'admin1@test.com',
            'channel' => 'system',
            'type' => 'payment_pending_approval',
            'status' => 'sent',
        ]);

        $this->assertDatabaseHas('notifications', [
            'recipient' => 'admin2@test.com',
            'channel' => 'system',
            'type' => 'payment_pending_approval',
            'status' => 'sent',
        ]);

        $this->assertSame(2, Notification::where('type', 'payment_pending_approval')->count());
    }

    public function test_a_failure_for_one_recipient_does_not_stop_the_others(): void
    {
        $service = app(NotificationService::class);

        // A null "recipient" violates the NOT NULL column and fails the DB
        // write for that one row, while the other recipient still succeeds.
        $results = $service->notifySystemUsers(
            [null, 'admin2@test.com'],
            'payment_pending_approval',
            'Contenido de prueba',
        );

        $this->assertFalse($results[0]['success']);
        $this->assertTrue($results[1]['success']);

        $this->assertDatabaseHas('notifications', [
            'recipient' => 'admin2@test.com',
        ]);
    }
}
