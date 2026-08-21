<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationViewer;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Markdown;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;
use JsonSerializable;
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

        return [
            'html' => (string) $mail->render(),
            'subject' => $this->nullIfBlank($mail->subject),
            'from' => $this->address($mail->from[0] ?? null, $mail->from[1] ?? null),
            'view' => $this->nullIfBlank($mail->markdown) ?? $this->nullIfBlank(is_string($mail->view) ? $mail->view : null),
            'channels' => $this->channels($notification, $notifiable),
        ];
    }

    /**
     * The channels a notification declares.
     *
     * @return list<string>
     */
    public function channels(object $previewable, object $notifiable): array
    {
        if ($previewable instanceof Mailable || ! method_exists($previewable, 'via')) {
            return ['mail'];
        }

        /** @var list<string> */
        return array_values(array_filter((array) $previewable->via($notifiable), is_string(...)));
    }

    /**
     * The payload a non-mail channel would hand to its provider, as JSON. Null
     * when the channel is not configured, not declared, or has no method.
     *
     * ponytail: a payload dump, not a provider-faithful render. Reproducing Slack
     * blocks or SMS segmenting means an adapter per provider, forever out of date.
     */
    public function channel(object $previewable, object $notifiable, string $channel): ?string
    {
        if (! $this->isEnabled($channel) || ! in_array($channel, $this->channels($previewable, $notifiable), true)) {
            return null;
        }

        $method = $this->channelMethod($previewable, $channel);

        if ($method === null) {
            return null;
        }

        $json = json_encode(
            $this->normalize($previewable->{$method}($notifiable)),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        return $json === false ? null : $json;
    }

    /**
     * Whether the config opted this channel in. Compared by name, so a config
     * entry of `smsapi` also matches a `SmsapiChannel::class` in via().
     */
    public function isEnabled(string $channel): bool
    {
        /** @var list<string> $enabled */
        $enabled = config('notification-viewer.channels', ['mail']);

        return in_array(
            Str::lower($this->channelName($channel)),
            array_map(fn (string $name) => Str::lower($this->channelName($name)), $enabled),
            true,
        );
    }

    /**
     * The name Laravel's `to{Channel}()` convention builds its method from, for
     * both driver strings (`slack`) and channel classes (`SmsapiChannel::class`).
     */
    public function channelName(string $channel): string
    {
        return class_exists($channel)
            ? Str::before(class_basename($channel), 'Channel')
            : Str::studly($channel);
    }

    protected function channelMethod(object $previewable, string $channel): ?string
    {
        $names = [$this->channelName($channel)];

        // The database channel is the one that falls back to toArray().
        if ($names[0] === 'Database') {
            $names[] = 'Array';
        }

        foreach ($names as $name) {
            if (method_exists($previewable, 'to'.$name)) {
                return 'to'.$name;
            }
        }

        return null;
    }

    protected function normalize(mixed $payload): mixed
    {
        return match (true) {
            $payload instanceof Arrayable => $payload->toArray(),
            $payload instanceof JsonSerializable => $payload->jsonSerialize(),
            is_object($payload) && method_exists($payload, 'toArray') => $payload->toArray(),
            is_object($payload) => get_object_vars($payload),
            default => $payload,
        };
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
