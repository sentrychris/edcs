<?php

namespace Tests\Unit;

use App\Models\FrontierUser;
use Carbon\Carbon;
use Tests\TestCase;

class FrontierUserTest extends TestCase
{
    public function test_is_token_expired_returns_true_when_expires_at_is_null(): void
    {
        $frontierUser = new FrontierUser;
        $frontierUser->token_expires_at = null;

        $this->assertTrue($frontierUser->isTokenExpired());
    }

    public function test_is_token_expired_returns_true_when_token_has_expired(): void
    {
        $frontierUser = new FrontierUser;
        $frontierUser->token_expires_at = Carbon::now()->subMinutes(10);

        $this->assertTrue($frontierUser->isTokenExpired());
    }

    public function test_is_token_expired_returns_false_when_token_is_still_valid(): void
    {
        $frontierUser = new FrontierUser;
        $frontierUser->token_expires_at = Carbon::now()->addHours(4);

        $this->assertFalse($frontierUser->isTokenExpired());
    }
}
