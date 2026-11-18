<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use BuildsOrganization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
    }

    public function test_sign_in_links_to_the_forgot_password_page(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Forgot password?');
        $this->get(route('password.request'))->assertOk()->assertSee('Send reset link');
    }

    public function test_unknown_email_gets_the_same_notice_and_no_mail(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'missing@hrms.test'])
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertNothingSent();
    }

    public function test_staff_account_receives_a_reset_link(): void
    {
        Notification::fake();
        $user = $this->makeUser('hr-admin');

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPasswordNotice::class);
    }

    public function test_account_without_a_role_does_not_receive_a_link(): void
    {
        Notification::fake();
        $user = User::factory()->create(['is_active' => true]);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status');

        Notification::assertNothingSent();
    }

    public function test_reset_link_saves_the_new_password_and_stays_signed_out(): void
    {
        $user = $this->makeUser('hr-admin');
        $token = Password::createToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertSee('Confirm password');

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPass123',
            'password_confirmation' => 'NewPass123',
        ])->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertTrue(Hash::check('NewPass123', $user->fresh()->password));
    }

    public function test_mismatched_confirmation_does_not_change_the_password(): void
    {
        $user = $this->makeUser('hr-admin');
        $token = Password::createToken($user);

        $this->from(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->post(route('password.update'), [
                'token' => $token,
                'email' => $user->email,
                'password' => 'NewPass123',
                'password_confirmation' => 'OtherPass123',
            ])->assertRedirect()
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
