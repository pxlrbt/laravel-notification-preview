<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationViewer\Tests\Fixtures\Notifications\Mail;

use Illuminate\Mail\Mailable;

/**
 * Old build() style mailable.
 */
class LegacyWelcome extends Mailable
{
    public function __construct(public string $customerName) {}

    public function build(): self
    {
        return $this->subject('Welcome aboard')->view('legacy-welcome');
    }
}
