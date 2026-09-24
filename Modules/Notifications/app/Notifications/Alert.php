<?php

declare(strict_types=1);

namespace Modules\Notifications\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Notifications\Enums\AlertLevel;

final class Alert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $kind,
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $url = null,
        public readonly AlertLevel $level = AlertLevel::Info,
        public readonly array $lines = [],
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return config('notifications.mail.enabled') ? ['database', 'mail'] : ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'level' => $this->level->value,
            'lines' => $this->lines,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject('ReconFlow: '.$this->title)->line($this->body);
        foreach ($this->lines as $line) {
            $mail->line($line);
        }

        return $this->url === null ? $mail : $mail->action('Open in ReconFlow', $this->url);
    }

    public function slackText(): string
    {
        $icon = match ($this->level) {
            AlertLevel::Critical => ':rotating_light:',
            AlertLevel::Warning => ':warning:',
            AlertLevel::Info => ':bar_chart:',
        };
        $text = "{$icon} *{$this->title}*\n{$this->body}";
        foreach ($this->lines as $line) {
            $text .= "\n• {$line}";
        }

        return $this->url === null ? $text : "{$text}\n<{$this->url}|Open in ReconFlow>";
    }
}
