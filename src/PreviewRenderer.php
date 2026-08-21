<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationViewer;

use Illuminate\Mail\Mailable;
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
        if (! method_exists($notification, 'toMail')) {
            throw new RuntimeException(class_basename($notification).' has no toMail() method.');
        }

        $mail = $notification->toMail($notifiable);

        if (! $mail instanceof MailMessage) {
            throw new RuntimeException(class_basename($notification).' does not return a MailMessage.');
        }

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

    protected function address(mixed $address, mixed $name): ?string
    {
        $address = is_string($address) && $address !== '' ? $address : config('mail.from.address');
        $name = is_string($name) && $name !== '' ? $name : config('mail.from.name');

        if (! is_string($address) || $address === '') {
            return null;
        }

        return is_string($name) && $name !== '' ? "{$name} <{$address}>" : $address;
    }

    protected function nullIfBlank(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
