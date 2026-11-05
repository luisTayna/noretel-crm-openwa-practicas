<?php

namespace Modules\Public\Emails;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\View;
use Modules\Public\Entities\CoverageRequest;

/**
 * Confirmation email sent to a customer after they submit a coverage
 * request. Sent synchronously (this class is not queueable) so it does not
 * depend on a running queue worker.
 */
class CoverageRequestConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var CoverageRequest
     */
    public $coverageRequest;

    public function __construct(CoverageRequest $coverageRequest)
    {
        $this->coverageRequest = $coverageRequest;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        // The Public module has no ServiceProvider registering a "public::"
        // view namespace (it is not wired into nwidart-laravel-modules), so
        // the namespace is registered here to follow the same
        // "<module>::<view>" convention used by the other modules.
        View::addNamespace('public', __DIR__ . '/../Resources/views');

        return $this->subject('Hemos recibido tu solicitud de cobertura')
            ->view('public::emails.coverage-request-confirmation')
            ->with([
                'name' => $this->coverageRequest->name,
                'address' => $this->coverageRequest->address,
            ]);
    }
}
