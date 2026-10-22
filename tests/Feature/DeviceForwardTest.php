<?php

namespace Tests\Feature;

use App\Models\Device;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class DeviceForwardTest extends TestCase
{
    use BuildsOrganization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
    }

    public function test_a_new_scan_is_sent_to_production(): void
    {
        Http::fake(['https://hrms.example/device-events' => Http::response(['saved' => 1, 'pending' => false])]);
        config([
            'dahua.forward_url' => 'https://hrms.example/device-events',
            'dahua.forward_token' => 'secret-token',
            'dahua.push_dir' => $dir = storage_path('framework/testing/dahua-forward'),
            'dahua.register_id' => 'GATE01',
        ]);
        File::deleteDirectory($dir);
        $org = $this->makeCompany('FWD');
        $employee = $this->makeEmployee($org, '900001');
        $device = Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Gate',
            'register_id' => 'GATE01',
            'status' => 'online',
        ]);
        $saved = $dir.DIRECTORY_SEPARATOR.'saved_files';
        File::ensureDirectoryExists($saved);
        $at = Carbon::parse('2026-10-21 10:00:00', 'Asia/Kolkata');
        File::put($saved.DIRECTORY_SEPARATOR.'21.json', json_encode([
            'RegisterID' => 'GATE01',
            'NotifyInfo' => ['Events' => [[
                'Code' => 'AccessControl',
                'Data' => [
                    'ErrorCode' => 0,
                    'Type' => 'Entry',
                    'RealUTC' => $at->utc()->timestamp,
                    'UserID' => '900001',
                    'UserName' => 'Test Person',
                    'SN' => 'GATE01',
                ],
            ]]],
        ]));

        \App\Models\DeviceTerminalUser::query()->create([
            'device_id' => $device->id,
            'user_id' => '900001',
            'name' => 'Test Person',
            'employee_id' => $employee->id,
        ]);
        \Illuminate\Support\Facades\Cache::flush();
        $this->assertSame(1, app(\App\Services\Devices\DeviceSync::class)->pullPunches());

        Http::assertSent(function ($request) {
            return $request->url() === 'https://hrms.example/device-events'
                && $request->hasHeader('X-Device-Token', 'secret-token')
                && $request['register_id'] === 'GATE01'
                && $request['seq'] === '21';
        });
    }

    public function test_production_stores_a_forwarded_scan_for_an_existing_employee(): void
    {
        config(['dahua.forward_token' => 'secret-token']);
        $org = $this->makeCompany('FWD');
        $employee = $this->makeEmployee($org, '900001');
        Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Gate',
            'register_id' => 'GATE01',
            'status' => 'online',
        ]);
        $at = Carbon::parse('2026-10-21 10:05:00', 'Asia/Kolkata');

        $this->postJson(route('device-events.store'), [
            'register_id' => 'GATE01',
            'seq' => '30',
            'image' => null,
            'event' => [
                'Code' => 'AccessControl',
                'Data' => [
                    'ErrorCode' => 0,
                    'Type' => 'Entry',
                    'RealUTC' => $at->utc()->timestamp,
                    'UserID' => '900001',
                    'UserName' => 'Test Person',
                    'SN' => 'GATE01',
                ],
            ],
        ], ['X-Device-Token' => 'secret-token'])
            ->assertOk()
            ->assertJson(['saved' => 1, 'pending' => false]);

        $this->assertDatabaseHas('attendance_punches', ['employee_id' => $employee->id]);
        $this->assertDatabaseMissing('employees', ['employee_code' => 'GHOST']);
    }

    public function test_a_forwarded_scan_for_an_unknown_person_is_not_stored(): void
    {
        config(['dahua.forward_token' => 'secret-token']);
        $org = $this->makeCompany('FWD');
        Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Gate',
            'register_id' => 'GATE01',
            'status' => 'online',
        ]);

        $this->postJson(route('device-events.store'), [
            'register_id' => 'GATE01',
            'seq' => '31',
            'event' => [
                'Code' => 'AccessControl',
                'Data' => [
                    'ErrorCode' => 0,
                    'Type' => 'Entry',
                    'RealUTC' => Carbon::parse('2026-10-21 10:06:00', 'Asia/Kolkata')->utc()->timestamp,
                    'UserID' => 'GHOST',
                    'UserName' => 'Ghost',
                    'SN' => 'GATE01',
                ],
            ],
        ], ['X-Device-Token' => 'secret-token'])
            ->assertOk()
            ->assertJson(['saved' => 0, 'pending' => true]);

        $this->assertDatabaseMissing('employees', ['employee_code' => 'GHOST']);
        $this->assertDatabaseCount('attendance_punches', 0);
    }

    public function test_opening_the_receiver_in_a_browser_shows_it_is_running(): void
    {
        $this->get(route('device-events.show'))
            ->assertOk()
            ->assertSee('Device event receiver is running');
    }

    public function test_a_forwarded_scan_without_the_token_is_refused(): void
    {
        config(['dahua.forward_token' => 'secret-token']);
        $this->postJson(route('device-events.store'), [
            'register_id' => 'GATE01',
            'seq' => '32',
            'event' => ['Code' => 'AccessControl', 'Data' => []],
        ])->assertUnauthorized();
    }
}
