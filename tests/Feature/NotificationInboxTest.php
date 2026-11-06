<?php

namespace Tests\Feature;

use App\Models\GatePass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class NotificationInboxTest extends TestCase
{
    use BuildsOrganization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
    }

    public function test_top_bar_and_more_page_show_notifications(): void
    {
        $org = $this->makeCompany('NT1');
        $hr = $this->makeUser('hr-admin', $org['company']);

        $this->actingAsScoped($hr)
            ->get(route('more'))
            ->assertOk()
            ->assertSee('Notifications');

        $this->actingAsScoped($hr)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Notifications', false);

        $this->actingAsScoped($hr)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee("You're all caught up", false);
    }

    public function test_gate_pass_request_appears_with_details_and_can_be_opened(): void
    {
        [$org, $vipul, $dharmesh, $manish, $arif] = $this->tree();
        $arifUser = $this->linkUser('manager', $arif);
        $head = $this->linkUser('director', $dharmesh);

        $this->actingAsScoped($arifUser)
            ->post(route('gate-passes.store'), [
                'employee_id' => $arif->id,
                'date' => now()->toDateString(),
                'out_time' => '15:00',
                'leave_type' => 'return',
                'duration_time' => '01:00',
                'work_type' => 'office',
                'reason' => 'Site visit with client',
            ])
            ->assertRedirect();

        $pass = GatePass::query()->first();
        $this->assertNotNull($pass);

        $this->actingAsScoped($head)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('New Gate Pass Request')
            ->assertSee('Office Work')
            ->assertSee($pass->number)
            ->assertSee('Site visit with client')
            ->assertSee('Department')
            ->assertSee('Parent')
            ->assertSee('Open Gate Pass');

        $this->actingAsScoped($head)
            ->getJson(route('notifications.unread'))
            ->assertOk()
            ->assertJson(['count' => 1]);

        $notice = $head->notifications()->first();
        $this->assertNotNull($notice);
        $this->actingAsScoped($head)
            ->post(route('notifications.read', $notice->id))
            ->assertRedirect(route('gate-passes.show', $pass));
        $this->assertNotNull($notice->fresh()->read_at);
    }

    /**
     * @return array{0: array<string, mixed>, 1: \App\Models\Employee, 2: \App\Models\Employee, 3: \App\Models\Employee, 4: \App\Models\Employee}
     */
    private function tree(): array
    {
        $org = $this->makeCompany('NTREE');
        $vipul = $this->makeEmployee($org, 'VIP');
        $dharmesh = $this->makeEmployee($org, 'DHA', $vipul->id);
        $manish = $this->makeEmployee($org, 'MAN', $dharmesh->id);
        $arif = $this->makeEmployee($org, 'ARF', $manish->id);

        return [$org, $vipul, $dharmesh, $manish, $arif];
    }

    private function linkUser(string $role, $employee): User
    {
        $user = $this->makeUser($role);
        $employee->update(['user_id' => $user->id]);

        return $user->fresh(['employee', 'roles']);
    }
}
