<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\QaThread;

use App\Http\Requests\QaThread\IndexRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * 質問掲示板一覧 IndexRequest の rules() / filters() を検証する Unit テスト。
 */
class IndexRequestTest extends TestCase
{
    public function test_accepts_known_status_values_and_empty(): void
    {
        foreach (['', 'unresolved', 'resolved', null] as $status) {
            $this->assertTrue(
                Validator::make(['status' => $status], (new IndexRequest)->rules())->passes(),
                var_export($status, true),
            );
        }
    }

    public function test_rejects_unknown_status_and_long_keyword(): void
    {
        $errors = Validator::make([
            'status' => 'open',
            'keyword' => str_repeat('a', 101),
        ], (new IndexRequest)->rules())->errors()->toArray();

        $this->assertArrayHasKey('status', $errors);
        $this->assertArrayHasKey('keyword', $errors);
    }

    public function test_filters_normalizes_missing_values_to_empty_string(): void
    {
        $request = IndexRequest::create('/qa-board', 'GET', ['keyword' => '  サブネット  ']);

        $this->assertSame([
            'certification_id' => '',
            'status' => '',
            'keyword' => 'サブネット',
        ], $request->filters());
    }
}
