<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class IndexAction
{
    /**
     * @param array{certification_id?: ?string, status?: ?string, keyword?: ?string} $filters
     */
    public function __invoke(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = QaThread::query()
            ->with(['user', 'certification'])
            ->withCount('replies')
            ->latest();

        // 管理者以外は公開中資格のみ
        if ($user->role !== UserRole::Admin) {
            $query->whereHas('certification', fn ($q) => $q->where('status', CertificationStatus::Published->value));
        }

        // コーチは担当資格のみ（仮実装）
        if ($user->role === UserRole::Coach) {
            $query->whereHas('certification', function ($q) use ($user) {
                $q->whereHas('coaches', fn ($qq) => $qq->where('users.id', $user->id));
            });
        }

        if (! empty($filters['certification_id'])) {
            $query->where('certification_id', $filters['certification_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['keyword'])) {
            $keyword = '%'.$filters['keyword'].'%';
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', $keyword)
                    ->orWhere('body', 'like', $keyword);
            });
        }

        return $query->paginate(20)->withQueryString();
    }
}
