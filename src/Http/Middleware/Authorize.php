<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationPreview\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use pxlrbt\LaravelNotificationPreview\NotificationPreview;
use Symfony\Component\HttpFoundation\Response;

class Authorize
{
    public function __construct(protected NotificationPreview $registry) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->registry->allows($request), 403);

        return $next($request);
    }
}
