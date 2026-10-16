<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\CertificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Certification;
use App\Models\QaThread;
use App\UseCases\QaThread\DestroyAction;
use App\UseCases\QaThread\IndexAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QaThreadController extends Controller
{
    public function index(Request $request, IndexAction $action): View
    {
        $filters = $request->only(['certification_id', 'status', 'keyword']);
        $threads = $action($request->user(), $filters);

        $certifications = Certification::query()->orderBy('name')->get();

        return view('qa-thread.index', [
            'threads' => $threads,
            'filters' => $filters,
            'certifications' => $certifications,
            'publishedStatus' => CertificationStatus::Published,
        ]);
    }

    public function show(QaThread $thread): View
    {
        $this->authorize('view', $thread);
        $thread->load(['user', 'certification', 'replies.user']);

        return view('qa-thread.show', compact('thread'));
    }

    public function destroy(QaThread $thread, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $thread);
        $action($thread);

        return redirect()->route('admin.qa-board.index')
            ->with('success', '質問を削除しました。');
    }
}
