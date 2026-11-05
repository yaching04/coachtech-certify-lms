<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\QaReply\StoreRequest;
use App\Http\Requests\QaReply\UpdateRequest;
use App\Models\QaReply;
use App\Models\QaThread;
use App\UseCases\QaReply\DestroyAction;
use App\UseCases\QaReply\StoreAction;
use App\UseCases\QaReply\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 質問掲示板スレッドの回答 Controller。
 *
 * - store / edit / update: 公開ルート(qa-board.replies.*)のみ。投稿は受講生 / コーチ、編集は回答者本人
 * - destroy: 公開ルート(回答者本人)と管理者モデレーションルート(admin.qa-board.replies.destroy)で共用
 *
 * `{reply}` は `{thread}` 配下に scopeBindings で解決するため、別スレッドの回答 ID を指定すると 404 になる。
 */
class QaReplyController extends Controller
{
    public function store(StoreRequest $request, QaThread $thread, StoreAction $action): RedirectResponse
    {
        $reply = $action($request->user(), $thread, $request->validated());

        return redirect()
            ->to(route('qa-board.show', $thread).'#reply-'.$reply->id)
            ->with('success', '回答を投稿しました。');
    }

    public function edit(QaThread $thread, QaReply $reply): View
    {
        $this->authorize('view', $thread);
        $this->authorize('update', $reply);

        return view('qa-thread.reply-edit', [
            'thread' => $thread,
            'reply' => $reply,
        ]);
    }

    public function update(UpdateRequest $request, QaThread $thread, QaReply $reply, UpdateAction $action): RedirectResponse
    {
        $action($reply, $request->validated());

        return redirect()
            ->to(route('qa-board.show', $thread).'#reply-'.$reply->id)
            ->with('success', '回答を更新しました。');
    }

    public function destroy(Request $request, QaThread $thread, QaReply $reply, DestroyAction $action): RedirectResponse
    {
        $this->authorize('view', $thread);
        $this->authorize('delete', $reply);

        $action($reply);

        $showRoute = $request->user()->role === UserRole::Admin ? 'admin.qa-board.show' : 'qa-board.show';

        return redirect()
            ->route($showRoute, $thread)
            ->with('success', '回答を削除しました。');
    }
}
