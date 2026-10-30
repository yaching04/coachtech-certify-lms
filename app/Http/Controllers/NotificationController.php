<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Notification\IndexRequest;
use App\UseCases\Notification\IndexAction;
use App\UseCases\Notification\MarkAllAsReadAction;
use App\UseCases\Notification\MarkAsReadAction;
use App\UseCases\Notification\ShowAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

/**
 * アプリ内通知の一覧・詳細・既読化(全ロール共通・本人の通知のみ)。
 *
 * 卒業済の受講生も過去の通知一覧を閲覧できるよう、ルートには active-learning を付けない。
 * 他人宛の通知の閲覧・既読化は NotificationPolicy で 403 にする。
 */
class NotificationController extends Controller
{
    public function index(IndexRequest $request, IndexAction $action): View
    {
        $user = $request->user();
        $tab = $request->tab();

        return view('notifications.index', [
            'notifications' => $action($user, $tab),
            'tab' => $tab,
            'unreadCount' => $action->unreadCount($user),
        ]);
    }

    public function show(DatabaseNotification $notification, ShowAction $action): View
    {
        $this->authorize('view', $notification);

        return view('notifications.show', [
            'notification' => $action($notification),
        ]);
    }

    public function markAsRead(DatabaseNotification $notification, MarkAsReadAction $action): RedirectResponse
    {
        $this->authorize('update', $notification);

        return redirect()->to($action($notification));
    }

    public function markAllAsRead(Request $request, MarkAllAsReadAction $action): RedirectResponse
    {
        $action($request->user());

        return redirect()
            ->route('notifications.index')
            ->with('success', 'すべての通知を既読にしました。');
    }
}
