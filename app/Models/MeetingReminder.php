<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MeetingReminderWindow;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 面談リマインダーの送信記録(二重配信防止用)。
 */
class MeetingReminder extends Model
{
    use HasUlids;

    protected $fillable = [
        'meeting_id',
        'user_id',
        'window',
        'sent_at',
    ];

    protected $casts = [
        'window' => MeetingReminderWindow::class,
        'sent_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Meeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
