<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Exceptions\QaBoard\QaThreadInvalidTransitionException;
use App\Exceptions\QaBoard\QaThreadNotDeletableException;
use App\Http\Requests\QaThread\IndexRequest;
use App\Http\Requests\QaThread\StoreRequest;
use App\Http\Requests\QaThread\UpdateRequest;
use App\Models\Certification;
use App\Models\QaThread;
use App\UseCases\QaThread\DestroyAction;
use App\UseCases\QaThread\IndexAction;
use App\UseCases\QaThread\ResolveAction;
use App\UseCases\QaThread\ShowAction;
use App\UseCases\QaThread\StoreAction;
use App\UseCases\QaThread\UnresolveAction;
use App\UseCases\QaThread\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 質問掲示板(qa-board)の質問スレッド Controller。受講生 / コーチ / 管理者で共用する。
 *
 * - index / show / destroy: 公開ルート(qa-board.*)と管理者モデレーションルート(admin.qa-board.*)の両方から呼ばれる。
 *   閲覧範囲は Policy + `QaThread::scopeVisibleTo` でロール別に絞り、画面(resources/views/qa-thread/)は
 *   ルート名(admin.*)で公開 / モデレーション表示を出し分ける
 * - create / store / edit / update / resolve / unresolve: 公開ルートのみ(投稿は受講生、編集・解決切替は投稿者本人)
 */
class QaThreadController extends Controller
{
    public function index(IndexRequest $request, IndexAction $action): View
    {
        $viewer = $request->user();
        $filters = $request->filters();

        return view('qa-thread.index', [
            'threads' => $action($viewer, $filters),
            'filters' => $filters,
            'certifications' => $action->filterableCertifications($viewer),
            'publishedStatus' => CertificationStatus::Published,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', QaThread::class);

        return view('qa-thread.create', [
            'certifications' => Certification::query()->published()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $thread = $action($request->user(), $request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を投稿しました。');
    }

    public function show(QaThread $thread, ShowAction $action): View
    {
        $this->authorize('view', $thread);

        return view('qa-thread.show', [
            'thread' => $action($thread),
        ]);
    }

    public function edit(QaThread $thread): View
    {
        $this->authorize('update', $thread);

        return view('qa-thread.edit', [
            'thread' => $thread->load('certification'),
        ]);
    }

    public function update(UpdateRequest $request, QaThread $thread, UpdateAction $action): RedirectResponse
    {
        $action($thread, $request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を更新しました。');
    }

    /**
     * @throws QaThreadNotDeletableException
     */
    public function destroy(Request $request, QaThread $thread, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $thread);

        $actor = $request->user();
        $action($actor, $thread);

        $indexRoute = $actor->role === UserRole::Admin ? 'admin.qa-board.index' : 'qa-board.index';

        return redirect()
            ->route($indexRoute)
            ->with('success', '質問を削除しました。');
    }

    /**
     * @throws QaThreadInvalidTransitionException
     */
    public function resolve(QaThread $thread, ResolveAction $action): RedirectResponse
    {
        $this->authorize('resolve', $thread);

        $action($thread);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '解決済にしました。');
    }

    /**
     * @throws QaThreadInvalidTransitionException
     */
    public function unresolve(QaThread $thread, UnresolveAction $action): RedirectResponse
    {
        $this->authorize('unresolve', $thread);

        $action($thread);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '未解決に戻しました。');
    }
}
