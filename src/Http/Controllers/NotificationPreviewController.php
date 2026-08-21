<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationPreview\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use pxlrbt\LaravelNotificationPreview\NotificationFactory;
use pxlrbt\LaravelNotificationPreview\NotificationInspector;
use pxlrbt\LaravelNotificationPreview\NotificationPreview;
use pxlrbt\LaravelNotificationPreview\PreviewRenderer;
use Throwable;

class NotificationPreviewController extends Controller
{
    public function __construct(
        protected NotificationPreview $registry,
        protected NotificationFactory $factory,
        protected NotificationInspector $inspector,
        protected PreviewRenderer $renderer,
    ) {}

    public function index(): View
    {
        /** @var view-string $view */
        $view = 'notification-preview::index';

        return view($view, [
            'entries' => $this->inspector->all(),
            'locales' => $this->registry->locales(),
            'testEmail' => config('notification-preview.test_email'),
        ]);
    }

    public function preview(Request $request): Response
    {
        $class = $this->validatedClass($request);
        $format = $request->string('format')->toString();

        try {
            if ($format !== '' && $format !== 'html') {
                return response($this->buildBody($request, $class, $format))
                    ->header('Content-Type', 'text/plain; charset=UTF-8');
            }

            $body = $this->build($request, $class)['html'];
        } catch (Throwable $exception) {
            /** @var view-string $view */
            $view = 'notification-preview::error';

            $body = view($view, ['exception' => $exception])->render();
        }

        return response($body)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function send(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'class' => ['required', 'string', Rule::in($this->registry->classes()->all())],
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

        return back()->with('notification-preview.status', "Test mail sent to {$recipient}.");
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
     * Any body other than the rendered HTML: the plain-text alternative, or the
     * JSON payload of one of the notification's other channels.
     *
     * @param  class-string  $class
     */
    protected function buildBody(Request $request, string $class, string $format): string
    {
        [$previewable, $notifiable] = $this->resolve($request, $class);

        if ($format === 'text') {
            return $this->renderer->text($previewable, $notifiable) ?? 'This message has no plain-text part.';
        }

        return $this->renderer->channel($previewable, $notifiable, $format)
            ?? 'This notification has no '.$this->renderer->channelName($format).' payload.';
    }

    /**
     * @param  class-string  $class
     * @return array{0: object, 1: object}
     */
    protected function resolve(Request $request, string $class): array
    {
        $variant = $request->input('variant') ?: null;
        $variant = is_string($variant) ? $variant : null;

        $previewable = $this->factory->make($class, $variant, $this->overrides($request));
        $notifiable = $this->inspector->notifiableFor($class, $variant);
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

        abort_unless($class !== '' && $this->registry->contains($class), 404);

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
