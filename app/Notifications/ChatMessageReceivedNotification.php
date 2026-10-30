<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\ChatMessage;

/**
 * チャットルームに新しいメッセージが届いたことを、送信者以外のメンバーへ知らせる通知。
 */
class ChatMessageReceivedNotification extends AppNotification
{
    public function __construct(private readonly ChatMessage $message) {}

    public function notificationType(): NotificationType
    {
        return NotificationType::ChatMessageReceived;
    }

    public function title(): string
    {
        return $this->message->sender->name.' さんからチャットが届きました';
    }

    public function message(): string
    {
        return $this->summarize($this->message->body);
    }

    public function url(): ?string
    {
        return route('chat.show', $this->message->chat_room_id, false);
    }
}
