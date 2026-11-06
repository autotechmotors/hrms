<?php

namespace Tests\Feature;

use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class DeviceEnrollTest extends TestCase
{
    use BuildsOrganization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
    }

    public function test_device_pages_expose_add_edit_and_delete(): void
    {
        $org = $this->makeCompany('DUI');
        $hr = $this->makeUser('hr-admin', $org['company']);
        $device = Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Gate',
            'register_id' => 'HIK1921681156',
            'place' => 'Lobby',
            'status' => 'unknown',
        ]);

        $this->actingAsScoped($hr)
            ->get(route('devices.index'))
            ->assertOk()
            ->assertSee('Add device')
            ->assertSee('Actions')
            ->assertSee('Hikvision')
            ->assertSee('editDevice'.$device->id, false)
            ->assertSee(route('devices.destroy', $device), false);

        $this->get(route('devices.show', $device))
            ->assertOk()
            ->assertSee('Enroll employee')
            ->assertDontSee('Get users from device')
            ->assertSee('People on this device')
            ->assertSee('Edit device')
            ->assertSee('Delete device')
            ->assertSee('Connection')
            ->assertSee('Hikvision ISAPI');

        $this->put(route('devices.update', $device), [
            'name' => 'Main gate',
            'register_id' => 'HIK1921681156',
            'place' => 'Lobby',
            'status' => 'offline',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('Main gate', $device->fresh()->name);
        $this->assertSame('offline', $device->fresh()->status);

        $this->delete(route('devices.destroy', $device))
            ->assertRedirect(route('devices.index'));
        $this->assertDatabaseMissing('devices', ['id' => $device->id]);
    }
}
