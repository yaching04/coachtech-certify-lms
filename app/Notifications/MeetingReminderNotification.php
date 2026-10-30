<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\MeetingReminderWindow;
use App\Enums\NotificationType;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * 予約済み面談の事前リマインダー(前日 / 開始 1 時間前)。受講生・担当コーチの双方へ送る。
 * 本文には受信者から見た「面談相手」の名前を載せるため、受信者ごとにインスタンスを作る。
 */
class MeetingReminderNotification extends AppNotification
{
    public function __construct(
        private readonly Meeting $meeting,
        private readonly MeetingReminderWindow $window,
        private readonly User $recipient,
    ) {}

    public function notificationType(): NotificationType
    {
        return NotificationType::MeetingReminder;
    }

    public function title(): string
    {
        return match ($this->window) {
            MeetingReminderWindow::Eve => '明日は面談の予定があります',
            MeetingReminderWindow::OneHourBefore => 'まもなく面談が始まります(開始 1 時間前)',
        };
    }

    public function message(): string
    {
        $counterpart = $this->recipient->id === $this->meeting->student_id
            ? $this->meeting->coach
            : $this->meeting->student;

        return $this->meeting->scheduled_at->isoFormat('YYYY/MM/DD(ddd) HH:mm').' 〜 '
            .($counterpart?->name ?? '退会済みユーザー').' さんとの面談';
    }

    public function url(): ?string
    {
        return route('meetings.show', $this->meeting, false);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return parent::toArray($notifiable) + [
            'meeting_id' => $this->meeting->id,
            'window' => $this->window->value,
        ];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $mail = parent::toMail($notifiable);

        if ($this->meeting->meeting_url_snapshot !== null) {
            $mail->line('面談 URL: '.$this->meeting->meeting_url_snapshot);
        }

        return $mail;
    }
}
