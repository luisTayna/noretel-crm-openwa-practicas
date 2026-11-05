<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Modules\Public\Emails\CoverageRequestConfirmationMail;
use Modules\Public\Entities\CoverageRequest;
use Modules\Public\Http\Controllers\CoverageRequestController;
use Tests\TestCase;

/**
 * Covers CoverageRequestController::sendConfirmationEmail() directly, for
 * the case validation normally prevents (no email on the request record).
 */
class CoverageRequestConfirmationEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        (include base_path('Modules/Public/Database/Migrations/2025_11_26_012625_create_coverage_requests_table.php'))->up();
    }

    public function test_no_mail_is_sent_when_the_coverage_request_has_no_email(): void
    {
        Mail::fake();

        $coverageRequest = CoverageRequest::create([
            'name' => 'Sin Email',
            'email' => '',
            'phone' => '999999999',
            'address' => 'Zona sin email',
            'latitude' => -12.0,
            'longitude' => -77.0,
            'status' => 'pending',
        ]);

        $controller = app(CoverageRequestController::class);
        $method = new \ReflectionMethod($controller, 'sendConfirmationEmail');
        $method->setAccessible(true);
        $method->invoke($controller, $coverageRequest);

        Mail::assertNothingSent();
    }
}
