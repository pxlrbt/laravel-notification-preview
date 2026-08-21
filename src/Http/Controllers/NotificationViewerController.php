<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationViewer\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use pxlrbt\LaravelNotificationViewer\NotificationFactory;
use pxlrbt\LaravelNotificationViewer\NotificationInspector;
use pxlrbt\LaravelNotificationViewer\NotificationViewer;
use pxlrbt\LaravelNotificationViewer\PreviewRenderer;
use Throwable;

class NotificationViewerController extends Controller
{
    public function __construct(
        protected NotificationViewer $viewer,
        protected NotificationFactory $factory,
        protected NotificationInspector $inspector,
        protected PreviewRenderer $renderer,
    ) {}

    public function index(): View
    {
        /** @var view-string $view */
        $view = 'notification-viewer::index';

        return view($view, [
            'entries' => $this->inspector->all(),
            'locales' => $this->viewer->locales(),
            'testEmail' => config('notification-viewer.test_email'),
        ]);
    }

    public function preview(Request $request): Response
    {
        $class = $this->validatedClass($request);
        $asText = $request->input('format') === 'text';

        try {
            if ($asText) {
                $text = $this->buildText($request, $class);

                return response($text ?? 'This message has no plain-text part.')
                    ->header('Content-Type', 'text/plain; charset=UTF-8');
            }

            $body = $this->build($request, $class)['html'];
        } catch (Throwable $exception) {
            /** @var view-string $view */
            $view = 'notification-viewer::error';

            $body = view($view, ['exception' => $exception])->render();
        }

        return response($body)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function send(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'class' => ['required', 'string', Rule::in($this->viewer->classes()->all())],
        ]);

        /** @var class-string $class */
        $class = $request->string('class')->toString();

        $rendered = $this->build($request, $class);
        $subject = $rendered['subject'] ?? class_basename($class);
        $recipient = $request->string('email')->toString();

        /*
         * Sends the exact markup shown in the preview rather than routing through
         * the mail channel, so what you test is what you saw.
         */
        Mail::html($rendered['html'], function ($message) use ($recipient, $subject): void {
            $message->to($recipient)->subject($subject);
        });

        return back()->with('notification-viewer.status', "Test mail sent to {$recipient}.");
    }

    /**
     * @param  class-string  $class
     * @return array{html: string, subject: ?string, from: ?string, view: ?string, channels: list<string>}
     */
    protected function build(Request $request, string $class): array
    {
        [$previewable, $notifiable] = $this->resolve($request, $class);

        return $this->renderer->render($previewable, $notifiable);
    }

    /**
     * @param  class-string  $class
     */
    protected function buildText(Request $request, string $class): ?string
    {
        [$previewable, $notifiable] = $this->resolve($request, $class);

        return $this->renderer->text($previewable, $notifiable);
    }

    /**
     * @param  class-string  $class
     * @return array{0: object, 1: object}
     */
    protected function resolve(Request $request, string $class): array
    {
        $variation = $request->input('variation') ?: null;
        $variation = is_string($variation) ? $variation : null;

        $previewable = $this->factory->make($class, $variation, $this->overrides($request));
        $notifiable = $this->inspector->notifiableFor($class, $variation);
        $locale = $request->input('locale');

        if (is_string($locale) && $locale !== '') {
            app()->setLocale($locale);

            if ($notifiable instanceof Model) {
                $notifiable->setAttribute('locale', $locale);
            }
        }

        return [$previewable, $notifiable];
    }

    /**
     * @return class-string
     */
    protected function validatedClass(Request $request): string
    {
        $class = $request->string('class')->toString();

        abort_unless($class !== '' && $this->viewer->contains($class), 404);

        /** @var class-string $class */
        return $class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function overrides(Request $request): array
    {
        $values = $request->input('values', []);

        /** @var array<string, mixed> */
        return is_array($values) ? $values : [];
    }
}
