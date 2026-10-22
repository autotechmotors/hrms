<?php

namespace Tests\Feature;

use App\Models\Device;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class EzapiPushTest extends TestCase
{
    use BuildsOrganization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        config(['dahua.poll_seconds' => 0]);
    }

    public function test_the_terminal_can_register_and_pull_a_capture_command(): void
    {
        $dir = storage_path('framework/testing/dahua-ezapi');
        File::deleteDirectory($dir);
        config(['dahua.push_dir' => $dir, 'dahua.register_id' => 'GATE01']);

        $org = $this->makeCompany('EZ');
        $employee = $this->makeEmployee($org, '100033');
        $hr = $this->makeUser('hr-admin', $org['company']);
        Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'TEST01',
            'register_id' => 'GATE01',
            'status' => 'online',
        ]);

        $this->postJson('/ezapi/push/init', [
            'RegisterID' => 'GATE01',
            'InitState' => 1,
            'DataPushCaps' => ['Event' => ['CodeList' => ['AccessControl']]],
        ])->assertOk()
            ->assertHeaderMissing('set-cookie')
            ->assertJsonPath('SycAction', 1)
            ->assertJsonPath('PandingTime', 10)
            ->assertJsonPath('CmdGetEnable', true);

        $this->get('/ezapi/push/status')
            ->assertOk()
            ->assertJsonPath('latest.register_id', 'GATE01')
            ->assertJsonPath('latest.action', 'init');

        $this->get('/ezapi/push/request?RegisterID=GATE01')
            ->assertOk()
            ->assertContent('');

        $this->actingAsScoped($hr)
            ->postJson(route('employees.credentials.collect', $employee), ['kind' => 'face'])
            ->assertOk();

        $pulled = $this->get('/ezapi/push/request?RegisterID=GATE01')->assertOk();
        $body = json_decode($pulled->getContent(), true);
        $this->assertSame('/ezapi/ac/personCollect', $body['Request']['URI']);
        $this->assertSame('POST', $body['Request']['Method']);
        $this->assertSame(0, $body['Request']['Body']['Type']);
        $this->assertSame('100033', $body['Request']['Body']['UserID']);
        $this->assertCount(0, File::files($dir.DIRECTORY_SEPARATOR.'commands'.DIRECTORY_SEPARATOR.'pending'));
        $this->assertCount(1, File::files($dir.DIRECTORY_SEPARATOR.'commands'.DIRECTORY_SEPARATOR.'sent'));
    }

    public function test_a_direct_scan_is_stored_without_the_office_pc(): void
    {
        $dir = storage_path('framework/testing/dahua-ezapi-scan');
        File::deleteDirectory($dir);
        config(['dahua.push_dir' => $dir, 'dahua.register_id' => 'GATE01']);

        $org = $this->makeCompany('EZS');
        $employee = $this->makeEmployee($org, '100033');
        $device = Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'TEST01',
            'register_id' => 'GATE01',
            'status' => 'online',
        ]);
        $at = Carbon::parse('2026-10-22 15:32:00', 'Asia/Kolkata');

        $this->postJson('/ezapi/push/event', [
            'RegisterID' => 'GATE01',
            'DataSeq' => '44',
            'NotifyInfo' => ['Events' => [[
                'Code' => 'AccessControl',
                'Data' => [
                    'ErrorCode' => 0,
                    'Type' => 'Entry',
                    'RealUTC' => $at->utc()->timestamp,
                    'UserID' => '100033',
                    'UserName' => 'Dharmesh',
                    'SN' => 'GATE01',
                ],
            ]]],
        ])->assertOk()->assertJsonPath('DataSeq', '44');

        $this->assertDatabaseHas('attendance_punches', [
            'employee_id' => $employee->id,
            'device_id' => $device->id,
        ]);
    }

    public function test_a_capture_result_is_imported_on_the_next_poll(): void
    {
        $dir = storage_path('framework/testing/dahua-ezapi-result');
        File::deleteDirectory($dir);
        config(['dahua.push_dir' => $dir, 'dahua.register_id' => 'GATE01']);

        $org = $this->makeCompany('EZR');
        $employee = $this->makeEmployee($org, '100033');
        Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'TEST01',
            'register_id' => 'GATE01',
            'status' => 'online',
        ]);
        $queue = $dir.DIRECTORY_SEPARATOR.'runtime'.DIRECTORY_SEPARATOR.'collects';
        File::ensureDirectoryExists($queue);
        File::put($queue.DIRECTORY_SEPARATOR.'GATE01__queue.json', json_encode([
            ['user_id' => '100033', 'kind' => 'face'],
        ]));

        $this->postJson('/ezapi/push/result', [
            'RegisterID' => 'GATE01',
            'Response' => [
                'StatusCode' => '200 OK',
                'Body' => ['Token' => 'abc-token', 'KeepTime' => 30],
            ],
        ])->assertOk();

        $follow = collect(File::files($dir.DIRECTORY_SEPARATOR.'commands'.DIRECTORY_SEPARATOR.'pending'))
            ->first(fn ($file) => str_contains(File::get($file->getPathname()), '/ezapi/ac/personCollect/abc-token'));
        $this->assertNotNull($follow);
        $this->assertSame($employee->employee_code, '100033');
    }

    public function test_an_unknown_caller_does_not_become_the_device_contact(): void
    {
        $dir = storage_path('framework/testing/dahua-ezapi-probe');
        File::deleteDirectory($dir);
        config(['dahua.push_dir' => $dir, 'dahua.register_id' => 'GATE01']);

        $org = $this->makeCompany('EZP');
        Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'TEST01',
            'register_id' => 'GATE01',
            'status' => 'online',
        ]);

        $this->postJson('/ezapi/push/init', [
            'RegisterID' => 'NOSNI',
            'InitState' => 1,
        ])->assertOk();

        $this->get('/ezapi/push/status')
            ->assertOk()
            ->assertJsonPath('latest', null);
    }
}
