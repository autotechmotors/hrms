<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use BuildsOrganization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
    }

    public function test_user_without_a_staff_role_cannot_sign_in(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertGuest();
    }

    public function test_login_rejects_unknown_credentials(): void
    {
        $this->post('/login', [
            'email' => 'missing@hrms.test',
            'password' => 'WrongPass1',
        ])->assertRedirect()->assertSessionHas('error');
    }

    public function test_login_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', [
                'email' => 'limit@hrms.test',
                'password' => 'WrongPass1',
            ])->assertStatus(302);
        }

        $this->post('/login', [
            'email' => 'limit@hrms.test',
            'password' => 'WrongPass1',
        ])->assertStatus(429);
    }

    public function test_privileged_user_must_confirm_an_authenticator(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.test',
            'password' => 'Admin@12345',
            'is_active' => true,
        ]);
        $user->roles()->attach(\App\Models\Role::query()->where('slug', 'super-admin')->value('id'));

        $this->post('/login', [
            'email' => 'admin@example.test',
            'password' => 'Admin@12345',
        ])->assertRedirect(route('two-factor.setup'));

        $this->get(route('two-factor.setup'))->assertOk();
        $user->refresh();

        $code = (new Google2FA())->getCurrentOtp($user->two_factor_secret);

        $this->post(route('two-factor.setup'), ['code' => $code])
            ->assertRedirect(route('two-factor.recovery'));

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_default_code_passes_the_authenticator_challenge(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.test',
            'password' => 'Admin@12345',
            'is_active' => true,
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => now(),
        ]);
        $user->roles()->attach(\App\Models\Role::query()->where('slug', 'super-admin')->value('id'));

        $this->post('/login', [
            'email' => 'admin@example.test',
            'password' => 'Admin@12345',
        ])->assertRedirect(route('two-factor.challenge'));

        $this->get(route('two-factor.challenge'))->assertOk()->assertSee('123456');

        $this->post(route('two-factor.challenge'), ['code' => '123456'])
            ->assertRedirect(route('dashboard'));
    }
}
