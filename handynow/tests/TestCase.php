<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * A browser session belongs to one user. Tests switch users inside a single session, so forget which
     * user's password the session was bound to (see AuthenticateSession) whenever the acting user changes.
     */
    public function actingAs(UserContract $user, $guard = null)
    {
        $this->app['auth']->forgetGuards();
        if ($this->app->bound('session.store')) {
            $this->app['session.store']->forget('password_hash_web');
        }

        // Earlier API calls in the same test leave the default guard on sanctum; the web app always uses 'web'.
        return parent::actingAs($user, $guard ?? 'web');
    }
}
