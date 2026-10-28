<?php

namespace Tests\Unit;

use Illuminate\Auth\GenericUser;
use Illuminate\Auth\TokenGuard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Mockery;
use PHPUnit\Framework\TestCase;

final class DependencyGuardTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_non_string_tokens_never_reach_user_provider(): void
    {
        foreach ([0, 1, true, false, [], ['0'], null, ''] as $token) {
            $provider = Mockery::mock(UserProvider::class);
            $provider->shouldNotReceive('retrieveByCredentials');
            $guard = new TokenGuard($provider, Request::create('/', 'GET', ['api_token' => $token]));
            $this->assertNull($guard->user());
            $this->assertFalse($guard->validate(['api_token' => $token]));
        }
    }

    public function test_valid_string_token_still_uses_provider_and_hash_mode(): void
    {
        foreach ([false, true] as $hash) {
            $user = new GenericUser(['id' => 42]);
            $provider = Mockery::mock(UserProvider::class);
            $provider->shouldReceive('retrieveByCredentials')->once()
                ->with(['api_token' => $hash ? hash('sha256', 'valid-token') : 'valid-token'])->andReturn($user);
            $guard = new TokenGuard($provider, Request::create('/', 'GET', ['api_token' => 'valid-token']), 'api_token', 'api_token', $hash);
            $this->assertSame($user, $guard->user());
        }
    }
}
