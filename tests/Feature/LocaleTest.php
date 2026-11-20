<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sign_in_page_follows_hindi_and_gujarati(): void
    {
        $this->get('/locale/hi')->assertRedirect();
        $this->get('/login')->assertOk()->assertSee('साइन इन', false);

        $this->get('/locale/gu')->assertRedirect();
        $this->get('/login')->assertOk()->assertSee('સાઇન ઇન', false);

        $this->get('/locale/en')->assertRedirect();
        $this->get('/login')->assertOk()->assertSee('Sign in');
    }
}
