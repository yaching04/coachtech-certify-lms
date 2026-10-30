<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * 管理者が配信した運営お知らせを受講生へ届ける通知。
 *
 * 遷移先の業務画面を持たない自己完結型の通知のため url は null。一覧から開くと通知詳細ページ
 * (notifications.show)へ遷移し、data.body に保存した本文全文を表示する。メールにも本文全文を載せる。
 */
class AdminAnnouncementNotification extends AppNotification
{
    public function __construct(private readonly Announcement $announcement) {}

    public function notificationType(): NotificationType
    {
        return NotificationType::AdminAnnouncement;
    }

    public function title(): string
    {
        return $this->announcement->title;
    }

    public function message(): string
    {
        return $this->summarize($this->announcement->body);
    }

    public function url(): ?string
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return parent::toArray($notifiable) + [
            'body' => $this->announcement->body,
            'announcement_id' => $this->announcement->id,
        ];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('【Certify LMS】'.$this->title())
            ->greeting($notifiable->name.' 様')
            ->line('運営からのお知らせです。');

        // プレーンテキスト本文の改行を保つため、行ごとに段落として追加する
        foreach (preg_split('/\R/u', $this->announcement->body) ?: [] as $line) {
            if (trim($line) !== '') {
                $mail->line($line);
            }
        }

        return $mail
            ->action('Certify LMS で確認する', $this->actionUrl())
            ->salutation('Certify LMS 運営チーム');
    }
}
