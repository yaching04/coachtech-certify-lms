<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Meeting;

/**
 * 受講生が面談を予約(担当コーチへ自動割当)したことを、割り当てられたコーチへ知らせる通知。
 */
class MeetingReservedNotification extends AppNotification
{
    public function __construct(private readonly Meeting $meeting) {}

    public function notificationType(): NotificationType
    {
        return NotificationType::MeetingReserved;
    }

    public function title(): string
    {
        return '面談の予約が入りました';
    }

    public function message(): string
    {
        return $this->meeting->scheduled_at->isoFormat('YYYY/MM/DD(ddd) HH:mm').' 〜 '
            .$this->meeting->student->name.' さん';
    }

    public function url(): ?string
    {
        return route('meetings.show', $this->meeting, false);
    }
}
