<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_missing_page_uses_the_error_card(): void
    {
        $this->get('/no-such-hrms-page')
            ->assertNotFound()
            ->assertSee('404')
            ->assertSee('Not Found')
            ->assertSee('This page is not in the system.')
            ->assertSee('Go home')
            ->assertSee('err-card', false);
    }

    public function test_a_missing_page_stays_json_for_ajax(): void
    {
        $this->getJson('/no-such-hrms-page')
            ->assertNotFound()
            ->assertJsonStructure(['message'])
            ->assertDontSee('err-card', false);
    }

    public function test_forbidden_expired_and_other_codes_use_the_same_card(): void
    {
        Route::middleware('web')->get('/_errors/forbidden', fn () => abort(403));
        Route::middleware('web')->get('/_errors/conflict', fn () => abort(409));
        Route::middleware('web')->get('/_errors/down', fn () => abort(503));
        Route::middleware('web')->get('/_errors/broken', fn () => abort(500));
        Route::middleware('web')->get('/_errors/odd', fn () => abort(418));

        $this->get('/_errors/forbidden')->assertForbidden()->assertSee('You do not have permission to open this page.');
        $this->get('/_errors/conflict')->assertStatus(409)->assertSee('This record was changed by someone else. Refresh and try again.');
        $this->get('/_errors/down')->assertStatus(503)->assertSee('The system is briefly unavailable. Try again in a moment.');
        $this->get('/_errors/broken')->assertStatus(500)->assertSee('Something went wrong on our side. Try again in a moment.');
        $this->get('/_errors/odd')->assertStatus(418)->assertSee('This page could not be opened.');

        Route::middleware('web')->get('/_errors/expired', fn () => abort(419));
        $this->get('/_errors/expired')->assertStatus(419)->assertSee('This page has expired. Go back, refresh, and try again.');
    }

    public function test_error_pages_follow_hindi_and_gujarati(): void
    {
        $this->get('/locale/hi')->assertRedirect();
        $this->get('/no-such-hrms-page')->assertNotFound()->assertSee('यह पेज सिस्टम में नहीं है।', false);

        $this->get('/locale/gu')->assertRedirect();
        $this->get('/no-such-hrms-page')->assertNotFound()->assertSee('આ પાનું સિસ્ટમમાં નથી.', false);
    }
}
