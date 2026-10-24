<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\QaReply;

use App\Http\Requests\QaReply\StoreRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * 回答投稿 StoreRequest の rules() を検証する Unit テスト(body: required / max:5000)。
 */
class StoreRequestTest extends TestCase
{
    public function test_passes_at_max_length(): void
    {
        $this->assertTrue(Validator::make(['body' => str_repeat('あ', 5000)], (new StoreRequest)->rules())->passes());
    }

    public function test_fails_when_empty_or_too_long(): void
    {
        $this->assertTrue(Validator::make(['body' => ''], (new StoreRequest)->rules())->fails());
        $this->assertTrue(Validator::make(['body' => str_repeat('あ', 5001)], (new StoreRequest)->rules())->fails());
    }
}
