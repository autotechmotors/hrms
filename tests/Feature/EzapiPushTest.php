<?php

namespace Tests\Feature;

use App\Models\AttendancePunch;
use App\Models\Device;
use App\Models\DeviceTerminalUser;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class EzapiPushTest extends TestCase
{
    use BuildsOrganization, RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        $this->dir = storage_path('framework/testing/dahua-ezapi');
        File::deleteDirectory($this->dir);
        config([
            'dahua.push_dir' => $this->dir,
            'dahua.register_id' => 'GATE01',
        ]);
    }

    public function test_the_terminal_can_register_on_the_website(): void
    {
        $org = $this->makeCompany('EZ');
        Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Gate',
            'register_id' => 'GATE01',
            'status' => 'unknown',
        ]);

        $response = $this->postJson('/ezapi/push/init', [
            'RegisterID' => 'GATE01',
            'InitState' => 1,
            'DataPushCaps' => [
                'Event' => ['CodeList' => ['AccessControl']],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('SycAction', 1);
        $response->assertJsonPath('PandingTime', 10);
        $response->assertJsonPath('CmdGetEnable', true);
        $response->assertJsonPath('DataPushEnable', true);
        $response->assertJsonPath('DataPushDetail.Event.CodeList.0', 'AccessControl');
        $this->assertNotSame('', $response->json('TimeCheck.UTC'));
        $this->assertSame('online', Device::query()->where('register_id', 'GATE01')->first()->status);
    }

    public function test_an_empty_poll_is_accepted(): void
    {
        $this->get('/ezapi/push/request?RegisterID=GATE01')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json; charset=utf-8');

        $this->assertSame('', $this->get('/ezapi/push/request?RegisterID=GATE01')->getContent());
    }

    public function test_the_terminal_receives_a_queued_face_command(): void
    {
        $pending = $this->dir.DIRECTORY_SEPARATOR.'commands'.DIRECTORY_SEPARATOR.'pending';
        File::ensureDirectoryExists($pending);
        File::put($pending.DIRECTORY_SEPARATOR.'GATE01__2026102212000000__collect-face.json', json_encode([
            'Request' => [
                'Index' => 1,
                'URI' => '/ezapi/ac/personCollect',
                'Method' => 'POST',
                'Headers' => ['Content-Type' => 'application/json'],
                'Body' => ['Type' => 0, 'UserID' => '1'],
            ],
        ]));

        $response = $this->get('/ezapi/push/request?RegisterID=GATE01');

        $response->assertOk();
        $response->assertJsonPath('Request.URI', '/ezapi/ac/personCollect');
        $response->assertJsonPath('Request.Body.Type', 0);
        $this->assertFileDoesNotExist($pending.DIRECTORY_SEPARATOR.'GATE01__2026102212000000__collect-face.json');
        $this->assertFileExists($this->dir.DIRECTORY_SEPARATOR.'commands'.DIRECTORY_SEPARATOR.'sent'.DIRECTORY_SEPARATOR.'GATE01__2026102212000000__collect-face.json');
    }

    public function test_a_scan_is_stored_on_this_server_without_forwarding(): void
    {
        Http::fake();
        config([
            'app.url' => 'https://hrms.autotechmotors.com',
            'dahua.forward_url' => 'https://hrms.autotechmotors.com/device-events',
            'dahua.forward_token' => 'secret-token',
        ]);
        $org = $this->makeCompany('EZ');
        $employee = $this->makeEmployee($org, '900001');
        $device = Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Gate',
            'register_id' => 'GATE01',
            'status' => 'online',
        ]);
        DeviceTerminalUser::query()->create([
            'device_id' => $device->id,
            'user_id' => '900001',
            'name' => 'Test Person',
            'employee_id' => $employee->id,
        ]);
        $at = Carbon::parse('2026-10-22 10:17:35', 'Asia/Kolkata');

        $response = $this->postJson('/ezapi/push/event', [
            'RegisterID' => 'GATE01',
            'DataSeq' => '99',
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
        ]);

        $response->assertOk();
        $this->assertSame('{"DataSeq": "99"}', $response->getContent());
        $this->assertSame(1, AttendancePunch::query()->where('employee_id', $employee->id)->count());
        Http::assertNothingSent();
    }
}
