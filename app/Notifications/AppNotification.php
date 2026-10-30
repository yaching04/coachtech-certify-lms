<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * 業務イベント通知の共通基底クラス。アプリ内通知(database)とメール(mail)の 2 チャネルへ同期送信する。
 *
 * database に保存する data は以下で固定し、一覧・既読化・遷移はこの data だけで完結させる。
 * - notification_type: 種別(NotificationType の value。一覧のアイコン出し分けに使う)
 * - title / message: 一覧に表示するタイトル・本文要約
 * - url: 既読化後の遷移先(アプリ内の相対パス)。遷移先を持たない通知は null
 *
 * 送信先の絞り込み(ロール・利用状態)は NotificationDispatchService が行うため、ここでは扱わない。
 */
abstract class AppNotification extends Notification
{
    /** 一覧・メールに載せる本文要約の最大文字数 */
    protected const MESSAGE_LIMIT = 100;

    abstract public function notificationType(): NotificationType;

    abstract public function title(): string;

    abstract public function message(): string;

    /**
     * 既読化後に遷移する業務画面のアプリ内相対パス。遷移先を持たない通知は null。
     */
    abstract public function url(): ?string;

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'notification_type' => $this->notificationType()->value,
            'title' => $this->title(),
            'message' => $this->message(),
            'url' => $this->url(),
        ];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('【Certify LMS】'.$this->title())
            ->greeting($notifiable->name.' 様')
            ->line($this->message())
            ->action('Certify LMS で確認する', url($this->url() ?? route('notifications.index', [], false)))
            ->salutation('Certify LMS 運営チーム');
    }

    protected function summarize(string $text): string
    {
        return Str::limit(trim((string) preg_replace('/\s+/u', ' ', $text)), self::MESSAGE_LIMIT);
    }
}
