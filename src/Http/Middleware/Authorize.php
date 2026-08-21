<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationViewer\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use pxlrbt\LaravelNotificationViewer\NotificationViewer;
use Symfony\Component\HttpFoundation\Response;

class Authorize
{
    public function __construct(protected NotificationViewer $viewer) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->viewer->allows($request), 403);

        return $next($request);
    }
}
