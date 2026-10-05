<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function actingAs(Authenticatable $user, $guard = null)
    {
        parent::actingAs($user, $guard);

        return $this->withSession([
            'mfa.verified_user_id' => $user->getAuthIdentifier(),
            'mfa.verified_at' => now()->timestamp,
        ]);
    }

    protected function actingAsWithoutMfa(Authenticatable $user, $guard = null): static
    {
        parent::actingAs($user, $guard);

        return $this;
    }
}
