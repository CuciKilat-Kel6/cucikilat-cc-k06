<?php

namespace Tests\Feature;

use Tests\TestCase;

class LocalLoginTest extends TestCase
{
    private function enableDemo(): void
    {
        config(['auth.local_demo.enabled' => true, 'auth.local_demo.email' => 'demo@example.test', 'auth.local_demo.password' => 'test-demo-password', 'session.driver' => 'array']);
    }

    public function test_local_login_is_prefilled_for_loopback_visitors(): void
    {
        $this->app->instance('env', 'local');
        $this->enableDemo();
        $this->get('/login')->assertOk()->assertSee('value="demo@example.test"', false)->assertSee('value="test-demo-password"', false);
    }

    public function test_production_login_never_exposes_demo_credentials(): void
    {
        $this->app->instance('env', 'production');
        $this->enableDemo();
        $this->get('/login')->assertOk()->assertDontSee('demo@example.test')->assertDontSee('test-demo-password');
    }

    public function test_remote_visitors_do_not_receive_demo_credentials(): void
    {
        $this->app->instance('env', 'local');
        $this->enableDemo();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->get('/login')->assertOk()->assertDontSee('demo@example.test')->assertDontSee('test-demo-password');
    }
}
