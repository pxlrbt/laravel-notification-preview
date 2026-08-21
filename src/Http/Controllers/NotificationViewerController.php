<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationViewer\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use pxlrbt\LaravelNotificationViewer\NotificationFactory;
use pxlrbt\LaravelNotificationViewer\NotificationInspector;
use pxlrbt\LaravelNotificationViewer\NotificationViewer;
use RuntimeException;
use Throwable;

class NotificationViewerController extends Controller
{
    public function __construct(
        protected NotificationViewer $viewer,
        protected NotificationFactory $factory,
        protected NotificationInspector $inspector,
    ) {}

    public function index(): View
    {
        return view('notification-viewer::index', [
            'notifications' => $this->inspector->all(),
            'locales' => $this->viewer->locales(),
            'testEmail' => config('notification-viewer.test_email'),
        ]);
    }

    public function preview(Request $request): Response
    {
        $class = $this->validatedClass($request);

        try {
            $html = (string) $this->build($request, $class)->render();
        } catch (Throwable $exception) {
            $html = view('notification-viewer::error', ['exception' => $exception])->render();
        }

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function send(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'notification' => ['required', 'string', Rule::in($this->viewer->classes()->all())],
        ]);

        /** @var class-string<Notification> $class */
        $class = $request->string('notification')->toString();

        $mail = $this->build($request, $class);
        $subject = $mail->subject ?? class_basename($class);
        $html = (string) $mail->render();
        $recipient = $request->string('email')->toString();

        /*
         * Sends the exact markup shown in the preview rather than routing through
         * the notification channel, so what you test is what you saw.
         */
        Mail::html($html, function ($message) use ($recipient, $subject): void {
            $message->to($recipient)->subject($subject);
        });

        return back()->with('notification-viewer.status', "Test mail sent to {$recipient}.");
    }

    /**
     * @param  class-string<Notification>  $class
     */
    protected function build(Request $request, string $class): MailMessage
    {
        $variation = $request->input('variation') ?: null;
        $variation = is_string($variation) ? $variation : null;

        $notification = $this->factory->make($class, $variation, $this->overrides($request));

        if (! method_exists($notification, 'toMail')) {
            throw new RuntimeException(class_basename($class).' has no toMail() method.');
        }

        $notifiable = $this->inspector->notifiableFor($class, $variation);
        $locale = $request->input('locale');

        if (is_string($locale) && $locale !== '') {
            app()->setLocale($locale);

            if ($notifiable instanceof Model) {
                $notifiable->setAttribute('locale', $locale);
            }
        }

        $mail = $notification->toMail($notifiable);

        if (! $mail instanceof MailMessage) {
            throw new RuntimeException(class_basename($class).' does not return a MailMessage.');
        }

        return $mail;
    }

    /**
     * @return class-string<Notification>
     */
    protected function validatedClass(Request $request): string
    {
        $class = $request->string('notification')->toString();

        abort_unless($class !== '' && $this->viewer->contains($class), 404);

        /** @var class-string<Notification> $class */
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
