<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\QaReply;

/**
 * 自分が投稿した質問スレッドに回答が付いたことをスレッド投稿者へ知らせる通知。
 */
class QaReplyReceivedNotification extends AppNotification
{
    public function __construct(private readonly QaReply $reply) {}

    public function notificationType(): NotificationType
    {
        return NotificationType::QaReplyReceived;
    }

    public function title(): string
    {
        return '質問「'.$this->reply->thread->title.'」に回答が付きました';
    }

    public function message(): string
    {
        return $this->reply->user->name.': '.$this->summarize($this->reply->body);
    }

    public function url(): ?string
    {
        return route('qa-board.show', $this->reply->thread, false).'#reply-'.$this->reply->id;
    }
}
