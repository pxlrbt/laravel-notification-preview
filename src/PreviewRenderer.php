<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationViewer;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Markdown;
use Illuminate\Notifications\Messages\MailMessage;
use RuntimeException;

class PreviewRenderer
{
    /**
     * Renders a notification or mailable and reports what the resulting message
     * looks like. Both are done in one pass because a mailable only hydrates its
     * envelope while rendering.
     *
     * @return array{html: string, subject: ?string, from: ?string, view: ?string, channels: list<string>}
     */
    public function render(object $previewable, object $notifiable): array
    {
        return $previewable instanceof Mailable
            ? $this->renderMailable($previewable)
            : $this->renderNotification($previewable, $notifiable);
    }

    /**
     * @return array{html: string, subject: ?string, from: ?string, view: ?string, channels: list<string>}
     */
    protected function renderMailable(Mailable $mailable): array
    {
        $html = (string) $mailable->render();

        return [
            'html' => $html,
            'subject' => $this->nullIfBlank($mailable->subject),
            'from' => $this->address($mailable->from[0]['address'] ?? null, $mailable->from[0]['name'] ?? null),
            'view' => $this->nullIfBlank($mailable->markdown) ?? $this->nullIfBlank($mailable->view),
            'channels' => ['mail'],
        ];
    }

    /**
     * @return array{html: string, subject: ?string, from: ?string, view: ?string, channels: list<string>}
     */
    protected function renderNotification(object $notification, object $notifiable): array
    {
        $mail = $this->mailMessage($notification, $notifiable);

        /** @var list<string> $channels */
        $channels = method_exists($notification, 'via') ? array_values((array) $notification->via($notifiable)) : [];

        return [
            'html' => (string) $mail->render(),
            'subject' => $this->nullIfBlank($mail->subject),
            'from' => $this->address($mail->from[0] ?? null, $mail->from[1] ?? null),
            'view' => $this->nullIfBlank($mail->markdown) ?? $this->nullIfBlank(is_string($mail->view) ? $mail->view : null),
            'channels' => $channels,
        ];
    }

    /**
     * The plain-text alternative of the message, mirroring how the mail channel
     * builds it. Null when the message has no text part.
     *
     * Kept separate from render() so listing every entry does not pay for it.
     */
    public function text(object $previewable, object $notifiable): ?string
    {
        return $previewable instanceof Mailable
            ? $this->mailableText($previewable)
            : $this->notificationText($previewable, $notifiable);
    }

    protected function mailableText(Mailable $mailable): ?string
    {
        // Hydrates $view, $textView and $markdown from envelope()/content()/build().
        $mailable->render();

        $textView = $this->nullIfBlank($mailable->textView);

        if ($textView !== null) {
            return $this->renderView($textView, $mailable->buildViewData());
        }

        $markdown = $this->nullIfBlank($mailable->markdown);

        if ($markdown !== null) {
            return (string) $this->markdown($this->nullIfBlank($mailable->theme))
                ->renderText($markdown, $mailable->buildViewData());
        }

        return null;
    }

    protected function notificationText(object $notification, object $notifiable): ?string
    {
        $mail = $this->mailMessage($notification, $notifiable);
        $view = $mail->view;

        /*
         * A plain view carries a text alternative only when it was given as
         * [html, text] or ['text' => ...]; a lone html view has none.
         */
        if (is_array($view)) {
            $textView = $this->nullIfBlank($view[1] ?? $view['text'] ?? null);

            return $textView === null ? null : $this->renderView($textView, $mail->data());
        }

        if ($this->nullIfBlank($view) !== null) {
            return null;
        }

        $markdown = $this->nullIfBlank($mail->markdown);

        if ($markdown === null) {
            return null;
        }

        return (string) $this->markdown($this->nullIfBlank($mail->theme))->renderText($markdown, $mail->data());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function renderView(string $view, array $data): string
    {
        return app(ViewFactory::class)->make($view, $data)->render();
    }

    protected function markdown(?string $theme): Markdown
    {
        /** @var Markdown $markdown */
        $markdown = app(Markdown::class);

        /** @var string $default */
        $default = config('mail.markdown.theme', 'default');

        return $markdown->theme($theme ?: $default);
    }

    protected function address(mixed $address, mixed $name): ?string
    {
        $address = is_string($address) && $address !== '' ? $address : config('mail.from.address');
        $name = is_string($name) && $name !== '' ? $name : config('mail.from.name');

        if (! is_string($address) || $address === '') {
            return null;
        }

        return is_string($name) && $name !== '' ? "{$name} <{$address}>" : $address;
    }

    protected function mailMessage(object $notification, object $notifiable): MailMessage
    {
        if (! method_exists($notification, 'toMail')) {
            throw new RuntimeException(class_basename($notification).' has no toMail() method.');
        }

        $mail = $notification->toMail($notifiable);

        if (! $mail instanceof MailMessage) {
            throw new RuntimeException(class_basename($notification).' does not return a MailMessage.');
        }

        return $mail;
    }

    protected function nullIfBlank(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
