<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * アプリ内通知の種別。notifications.data.notification_type に保存し、
 * 通知一覧のアイコン出し分け(notifications/_partials/notification-row.blade.php)に使う。
 */
enum NotificationType: string
{
    case ChatMessageReceived = 'chat_message_received';
    case QaReplyReceived = 'qa_reply_received';
    case MeetingReserved = 'meeting_reserved';
    case MeetingCanceled = 'meeting_canceled';
    case AdminAnnouncement = 'admin_announcement';
    case MeetingReminder = 'meeting_reminder';

    public function label(): string
    {
        return match ($this) {
            self::ChatMessageReceived => 'チャット新着',
            self::QaReplyReceived => '質問への回答',
            self::MeetingReserved => '面談予約',
            self::MeetingCanceled => '面談キャンセル',
            self::AdminAnnouncement => '運営からのお知らせ',
            self::MeetingReminder => '面談リマインダー',
        };
    }
}
