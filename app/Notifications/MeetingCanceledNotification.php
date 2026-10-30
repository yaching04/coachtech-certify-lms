<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Meeting;

/**
 * 予約済の面談がキャンセルされたことを、キャンセルしなかった側の当事者へ知らせる通知。
 */
class MeetingCanceledNotification extends AppNotification
{
    public function __construct(private readonly Meeting $meeting) {}

    public function notificationType(): NotificationType
    {
        return NotificationType::MeetingCanceled;
    }

    public function title(): string
    {
        return '面談がキャンセルされました';
    }

    public function message(): string
    {
        $canceledBy = $this->meeting->canceledBy?->name ?? '相手';

        return $this->meeting->scheduled_at->isoFormat('YYYY/MM/DD(ddd) HH:mm').' の面談を '
            .$canceledBy.' さんがキャンセルしました';
    }

    public function url(): ?string
    {
        return route('meetings.show', $this->meeting, false);
    }
}
