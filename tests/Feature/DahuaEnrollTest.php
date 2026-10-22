<?php

namespace Tests\Feature;

use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class DahuaEnrollTest extends TestCase
{
    use BuildsOrganization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
    }

    public function test_enroll_queues_one_add_person_command(): void
    {
        $dir = storage_path('framework/testing/dahua-push');
        File::deleteDirectory($dir);
        config([
            'dahua.push_dir' => $dir,
            'dahua.register_id' => 'test123',
        ]);

        $org = $this->makeCompany('DVC');
        $employee = $this->makeEmployee($org, 'AT0009');
        $hr = $this->makeUser('hr-admin', $org['company']);
        $device = Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Gate',
            'register_id' => 'test123',
            'status' => 'unknown',
        ]);

        $this->actingAsScoped($hr)
            ->post(route('devices.enroll', $device), ['employee_id' => $employee->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $files = File::files($dir.DIRECTORY_SEPARATOR.'commands'.DIRECTORY_SEPARATOR.'pending');
        $this->assertCount(1, $files);
        $payload = json_decode(File::get($files[0]->getPathname()), true);
        $this->assertSame('/ezapi/ac/persons', $payload['Request']['URI']);
        $this->assertSame('AT0009', $payload['Request']['Body']['PersonnelInfo']['UserID']);
        $this->assertSame('Test AT0009', $payload['Request']['Body']['PersonnelInfo']['User']['UserName']);
        $user = $payload['Request']['Body']['PersonnelInfo']['User'];
        $this->assertSame(2, $user['Authority']);
        $this->assertSame(0, $user['UserType']);
        $this->assertSame('2037-12-31 23:59:59', $user['ValidTo']);
        $this->assertSame([255], $user['SpecialDaysSchedule']);
        $this->assertSame([255], $user['TimeSections']);
        $this->assertSame([255], $user['MultiTimeSections'][0]['SpecialDaysSchedule']);
        $this->assertSame([255], $user['MultiTimeSections'][0]['TimeSections']);
        $this->assertStringStartsWith('test123__', $files[0]->getFilename());

        $this->delete(route('devices.enrollment.destroy', $device->users()->first()))
            ->assertRedirect();
        $pending = collect(File::files($dir.DIRECTORY_SEPARATOR.'commands'.DIRECTORY_SEPARATOR.'pending'))
            ->map(fn ($file) => json_decode(File::get($file->getPathname()), true));
        $this->assertTrue($pending->contains(fn ($payload) => ($payload['Request']['URI'] ?? null) === '/ezapi/ac/users/delete'));
    }

    public function test_saving_an_employee_pushes_the_name_and_photo_to_the_terminal(): void
    {
        $dir = storage_path('framework/testing/dahua-edit');
        File::deleteDirectory($dir);
        config([
            'dahua.push_dir' => $dir,
            'dahua.register_id' => 'GATE01',
        ]);

        $org = $this->makeCompany('EDT');
        $employee = $this->makeEmployee($org, '100033');
        $hr = $this->makeUser('hr-admin', $org['company']);
        $device = Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Gate',
            'register_id' => 'GATE01',
            'status' => 'online',
        ]);
        \App\Models\DeviceUser::query()->create([
            'device_id' => $device->id,
            'employee_id' => $employee->id,
        ]);

        $this->actingAsScoped($hr)
            ->get(route('employees.edit', $employee))
            ->assertOk()
            ->assertSee('Gate')
            ->assertSee('Save sends the name and this photo to Gate');

        $this->put(route('employees.update', $employee), [
            'company_id' => $org['company']->id,
            'branch_id' => $org['branch']->id,
            'department_id' => $org['department']->id,
            'designation_id' => $org['designation']->id,
            'employee_type_id' => $org['type']->id,
            'employment_status_id' => $org['status']->id,
            'first_name' => 'Dharmesh',
            'last_name' => 'Koladia',
            'joining_date' => '2026-04-01',
            'photograph' => \Illuminate\Http\UploadedFile::fake()->image('face.jpg', 40, 40),
        ])->assertRedirect()->assertSessionHas('success');

        $files = collect(File::files($dir.DIRECTORY_SEPARATOR.'commands'.DIRECTORY_SEPARATOR.'pending'));
        $payloads = $files->map(fn ($file) => json_decode(File::get($file->getPathname()), true));
        $person = $payloads->first(fn ($payload) => ($payload['Request']['URI'] ?? null) === '/ezapi/ac/persons');
        $face = $payloads->first(fn ($payload) => ($payload['Request']['URI'] ?? null) === '/ezapi/ac/faces');
        $this->assertNotNull($person);
        $this->assertSame('Dharmesh Koladia', $person['Request']['Body']['PersonnelInfo']['User']['UserName']);
        $this->assertNotNull($face);
        $this->assertSame('100033', $face['Request']['Body']['FaceList'][0]['UserID']);
        $this->assertSame([], $face['Request']['Body']['FaceList'][0]['FeatureData']);
        $photo = $face['Request']['Body']['FaceList'][0]['PhotoData'][0];
        $this->assertNotEmpty($photo);
        $this->assertLessThan(80 * 1024, strlen(base64_decode($photo)));
        $this->assertDatabaseHas('employee_credentials', [
            'employee_id' => $employee->id,
            'kind' => 'face',
        ]);

        $this->delete(route('devices.enrollment.destroy', $device->users()->first()))
            ->assertRedirect();
        $pending = collect(File::files($dir.DIRECTORY_SEPARATOR.'commands'.DIRECTORY_SEPARATOR.'pending'))
            ->map(fn ($file) => json_decode(File::get($file->getPathname()), true));
        $this->assertTrue($pending->contains(fn ($payload) => ($payload['Request']['URI'] ?? null) === '/ezapi/ac/users/delete'));
    }

    public function test_employee_list_starts_face_collection_on_the_terminal(): void
    {
        $dir = storage_path('framework/testing/dahua-collect');
        File::deleteDirectory($dir);
        config([
            'dahua.push_dir' => $dir,
            'dahua.register_id' => 'GATE01',
        ]);

        $org = $this->makeCompany('COL');
        $employee = $this->makeEmployee($org, '100033');
        $hr = $this->makeUser('hr-admin', $org['company']);
        $device = Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Gate',
            'register_id' => 'GATE01',
            'status' => 'online',
        ]);
        \App\Models\EmployeeCredential::query()->create([
            'employee_id' => $employee->id,
            'kind' => 'finger',
            'slot' => '0',
            'content' => base64_encode('finger'),
        ]);

        $this->actingAsScoped($hr)
            ->get(route('employees.index'))
            ->assertOk()
            ->assertSee('Access Credentials')
            ->assertSee('data-kind="finger"', false)
            ->assertSee('>1<', false);

        $this->postJson(route('employees.credentials.collect', $employee), ['kind' => 'face'])
            ->assertOk()
            ->assertJsonPath('counts.finger', 1)
            ->assertJsonPath('counts.face', 0);

        $collect = collect(File::files($dir.DIRECTORY_SEPARATOR.'commands'.DIRECTORY_SEPARATOR.'pending'))
            ->first(fn ($file) => str_contains($file->getFilename(), 'collect-face'));
        $this->assertNotNull($collect);
        $payload = json_decode(File::get($collect->getPathname()), true);
        $this->assertSame('/ezapi/ac/personCollect', $payload['Request']['URI']);
        $this->assertSame(0, $payload['Request']['Body']['Type']);
        $this->assertSame('100033', $payload['Request']['Body']['UserID']);
        $beforeCapture = collect(File::files($dir.DIRECTORY_SEPARATOR.'commands'.DIRECTORY_SEPARATOR.'pending'))
            ->map(fn ($file) => json_decode(File::get($file->getPathname()), true));
        $this->assertNull($beforeCapture->first(fn ($payload) => ($payload['Request']['URI'] ?? null) === '/ezapi/ac/fingerprints'));
        $this->assertNull($beforeCapture->first(fn ($payload) => ($payload['Request']['URI'] ?? null) === '/ezapi/ac/faces'));

        $inbox = $dir.DIRECTORY_SEPARATOR.'runtime'.DIRECTORY_SEPARATOR.'inbox';
        File::ensureDirectoryExists($inbox);
        File::put($inbox.DIRECTORY_SEPARATOR.'GATE01__token.json', json_encode([
            'Response' => ['StatusCode' => '200 OK', 'Body' => ['Token' => 'abc-token', 'KeepTime' => 30]],
        ]));
        app(\App\Services\Devices\DahuaPush::class)->importInbox($device);

        $this->assertNull(collect(File::files($dir.DIRECTORY_SEPARATOR.'commands'.DIRECTORY_SEPARATOR.'pending'))
            ->first(fn ($file) => str_ends_with($file->getFilename(), '__collect-face-100033.json')));
        $follow = collect(File::files($dir.DIRECTORY_SEPARATOR.'commands'.DIRECTORY_SEPARATOR.'pending'))
            ->first(fn ($file) => str_contains(File::get($file->getPathname()), '/ezapi/ac/personCollect/abc-token'));
        $this->assertNotNull($follow);

        File::put($inbox.DIRECTORY_SEPARATOR.'GATE01__data.json', json_encode([
            'Response' => [
                'StatusCode' => '200 OK',
                'Body' => ['Status' => 1, 'DataInfo' => [['FaceInfo' => base64_encode('face-bytes'), 'Type' => 0]]],
            ],
        ]));
        app(\App\Services\Devices\DahuaPush::class)->importInbox($device->fresh());
        $this->assertDatabaseHas('employee_credentials', [
            'employee_id' => $employee->id,
            'kind' => 'face',
        ]);

        $face = collect(File::files($dir.DIRECTORY_SEPARATOR.'commands'.DIRECTORY_SEPARATOR.'pending'))
            ->map(fn ($file) => json_decode(File::get($file->getPathname()), true))
            ->first(fn ($payload) => ($payload['Request']['URI'] ?? null) === '/ezapi/ac/faces');
        $this->assertNotNull($face);
        $this->assertSame('100033', $face['Request']['Body']['FaceList'][0]['UserID']);
        $this->assertSame(base64_encode('face-bytes'), $face['Request']['Body']['FaceList'][0]['PhotoData'][0]);
        $this->assertSame([], $face['Request']['Body']['FaceList'][0]['FeatureData']);
        $finger = collect(File::files($dir.DIRECTORY_SEPARATOR.'commands'.DIRECTORY_SEPARATOR.'pending'))
            ->map(fn ($file) => json_decode(File::get($file->getPathname()), true))
            ->first(fn ($payload) => ($payload['Request']['URI'] ?? null) === '/ezapi/ac/fingerprints');
        $this->assertNotNull($finger);
        $this->assertSame('100033', $finger['Request']['Body']['UsersFingerPrints'][0]['UserID']);
    }

    public function test_device_pages_expose_add_edit_and_delete(): void
    {
        $org = $this->makeCompany('DUI');
        $hr = $this->makeUser('hr-admin', $org['company']);
        $device = Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Gate',
            'register_id' => 'test123',
            'place' => 'Lobby',
            'status' => 'unknown',
        ]);

        $this->actingAsScoped($hr)
            ->get(route('devices.index'))
            ->assertOk()
            ->assertSee('Add device')
            ->assertSee('Actions')
            ->assertSee('editDevice'.$device->id, false)
            ->assertSee(route('devices.destroy', $device), false);

        $this->get(route('devices.show', $device))
            ->assertOk()
            ->assertSee('Enroll employee')
            ->assertDontSee('Get users from device')
            ->assertSee('People on this device')
            ->assertSee('Edit device')
            ->assertSee('Delete device')
            ->assertSee('Connection');

        $this->put(route('devices.update', $device), [
            'name' => 'Main gate',
            'register_id' => 'test123',
            'place' => 'Lobby',
            'status' => 'offline',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('Main gate', $device->fresh()->name);
        $this->assertSame('offline', $device->fresh()->status);

        $this->delete(route('devices.destroy', $device))
            ->assertRedirect(route('devices.index'));
        $this->assertDatabaseMissing('devices', ['id' => $device->id]);
    }

    public function test_device_user_list_is_imported_from_the_terminal_reply(): void
    {
        $dir = storage_path('framework/testing/dahua-inbox');
        File::deleteDirectory($dir);
        config([
            'dahua.push_dir' => $dir,
            'dahua.register_id' => 'test123',
        ]);

        $org = $this->makeCompany('DIN');
        $employee = $this->makeEmployee($org, '100033');
        $hr = $this->makeUser('hr-admin', $org['company']);
        $device = Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Gate',
            'register_id' => 'test123',
            'status' => 'online',
        ]);

        $inbox = $dir.DIRECTORY_SEPARATOR.'runtime'.DIRECTORY_SEPARATOR.'inbox';
        File::ensureDirectoryExists($inbox);
        File::put($inbox.DIRECTORY_SEPARATOR.'test123__reply.json', json_encode([
            'RegisterID' => 'test123',
            'Response' => [
                'StatusCode' => '200 OK',
                'Body' => [
                    'Total' => 2,
                    'InfoList' => [
                        ['UserID' => '100033', 'UserName' => 'Dharmesh Koladia'],
                        ['UserID' => '100099', 'UserName' => 'Gate Guest'],
                    ],
                ],
            ],
        ]));

        $this->actingAsScoped($hr)
            ->get(route('devices.show', $device))
            ->assertOk()
            ->assertSee('100033')
            ->assertSee('Dharmesh Koladia')
            ->assertSee('100099')
            ->assertSee('Gate Guest');

        $this->assertDatabaseHas('employees', [
            'company_id' => $org['company']->id,
            'employee_code' => '100099',
            'first_name' => 'Gate',
            'last_name' => 'Guest',
        ]);

        $this->assertDatabaseHas('device_terminal_users', [
            'device_id' => $device->id,
            'user_id' => '100033',
            'employee_id' => $employee->id,
        ]);

        $person = $device->terminalUsers()->where('user_id', '100099')->firstOrFail();
        $this->put(route('devices.terminal.update', $person), ['name' => 'Guest Two'])
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertSame('Guest Two', $person->fresh()->name);

        $this->artisan('devices:sync')->assertSuccessful();
        $pending = collect(File::files($dir.DIRECTORY_SEPARATOR.'commands'.DIRECTORY_SEPARATOR.'pending'))
            ->map(fn ($file) => json_decode(File::get($file->getPathname()), true));
        $this->assertTrue($pending->contains(fn ($payload) => ($payload['Request']['URI'] ?? null) === '/ezapi/ac/users/query'));
        $this->assertTrue($pending->contains(fn ($payload) => ($payload['Request']['URI'] ?? null) === '/ezapi/ac/persons/query'));
    }

    public function test_a_new_employee_is_sent_to_the_company_terminal(): void
    {
        $dir = storage_path('framework/testing/dahua-add');
        File::deleteDirectory($dir);
        config(['dahua.push_dir' => $dir, 'dahua.register_id' => 'GATE01']);

        $org = $this->makeCompany('ADD');
        $employee = $this->makeEmployee($org, 'AT0002');
        Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Gate',
            'register_id' => 'GATE01',
            'status' => 'online',
        ]);

        $this->assertSame(1, app(\App\Services\Devices\EmployeePush::class)->sync($employee));

        $payloads = collect(File::files($dir.DIRECTORY_SEPARATOR.'commands'.DIRECTORY_SEPARATOR.'pending'))
            ->map(fn ($file) => json_decode(File::get($file->getPathname()), true));
        $person = $payloads->first(fn ($payload) => ($payload['Request']['URI'] ?? null) === '/ezapi/ac/persons');
        $this->assertNotNull($person);
        $this->assertSame('AT0002', $person['Request']['Body']['PersonnelInfo']['User']['UserID']);
        $this->assertSame('Test AT0002', $person['Request']['Body']['PersonnelInfo']['User']['UserName']);
        $this->assertSame('1970-02-01 00:00:00', $person['Request']['Body']['PersonnelInfo']['User']['ValidFrom']);
        $this->assertSame(255, $person['Request']['Body']['PersonnelInfo']['User']['UseTime']);
    }

    public function test_finger_and_card_collect_wait_then_store_on_the_terminal(): void
    {
        $dir = storage_path('framework/testing/dahua-finger');
        File::deleteDirectory($dir);
        config(['dahua.push_dir' => $dir, 'dahua.register_id' => 'GATE01']);

        $org = $this->makeCompany('FNG');
        $employee = $this->makeEmployee($org, 'AT0006');
        $hr = $this->makeUser('hr-admin', $org['company']);
        $device = Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Gate',
            'register_id' => 'GATE01',
            'status' => 'online',
        ]);
        \App\Models\EmployeeCredential::query()->create([
            'employee_id' => $employee->id,
            'kind' => 'password',
            'slot' => '0',
            'content' => base64_encode('12345678'),
        ]);
        \App\Models\EmployeeCredential::query()->create([
            'employee_id' => $employee->id,
            'kind' => 'face',
            'slot' => '0',
            'content' => base64_encode('stored-face'),
        ]);

        $this->actingAsScoped($hr)
            ->postJson(route('employees.credentials.collect', $employee), ['kind' => 'finger'])
            ->assertOk();

        $payloads = fn () => collect(File::files($dir.DIRECTORY_SEPARATOR.'commands'.DIRECTORY_SEPARATOR.'pending'))
            ->map(fn ($file) => json_decode(File::get($file->getPathname()), true));
        $this->assertNull($payloads()->first(fn ($payload) => ($payload['Request']['URI'] ?? null) === '/ezapi/ac/persons'));
        $collect = $payloads()->first(fn ($payload) => ($payload['Request']['URI'] ?? null) === '/ezapi/ac/personCollect');
        $this->assertSame(2, $collect['Request']['Body']['Type']);
        $this->assertNull($payloads()->first(fn ($payload) => ($payload['Request']['URI'] ?? null) === '/ezapi/ac/faces'));
        $this->assertNull($payloads()->first(fn ($payload) => ($payload['Request']['URI'] ?? null) === '/ezapi/ac/fingerprints'));

        $inbox = $dir.DIRECTORY_SEPARATOR.'runtime'.DIRECTORY_SEPARATOR.'inbox';
        File::ensureDirectoryExists($inbox);
        $push = app(\App\Services\Devices\DahuaPush::class);
        File::put($inbox.DIRECTORY_SEPARATOR.'GATE01__token.json', json_encode([
            'Response' => ['StatusCode' => '200 OK', 'Body' => ['Token' => 'finger-token', 'KeepTime' => 90]],
        ]));
        $push->importInbox($device);
        File::put($inbox.DIRECTORY_SEPARATOR.'GATE01__waiting.json', json_encode([
            'Response' => ['StatusCode' => '200 OK', 'Body' => ['Status' => 0, 'CollectCount' => 1]],
        ]));
        $push->importInbox($device->fresh());
        $this->assertDatabaseMissing('employee_credentials', [
            'employee_id' => $employee->id,
            'kind' => 'finger',
        ]);
        $stillWaiting = $payloads()->contains(fn ($payload) => ($payload['Request']['URI'] ?? null) === '/ezapi/ac/personCollect/finger-token');
        $this->assertTrue($stillWaiting);

        File::put($inbox.DIRECTORY_SEPARATOR.'GATE01__finger.json', json_encode([
            'Response' => [
                'StatusCode' => '200 OK',
                'Body' => ['Status' => 1, 'DataInfo' => [['FingerInfo' => base64_encode('finger-bytes'), 'Type' => 0]]],
            ],
        ]));
        $push->importInbox($device->fresh());
        $this->assertDatabaseHas('employee_credentials', [
            'employee_id' => $employee->id,
            'kind' => 'finger',
        ]);
        $finger = $payloads()->first(fn ($payload) => ($payload['Request']['URI'] ?? null) === '/ezapi/ac/fingerprints');
        $this->assertNotNull($finger);
        $person = $payloads()->first(fn ($payload) => ($payload['Request']['URI'] ?? null) === '/ezapi/ac/persons');
        $this->assertSame('12345678', $person['Request']['Body']['PersonnelInfo']['User']['Password']);
        $this->assertSame('AT0006', $finger['Request']['Body']['UsersFingerPrints'][0]['UserID']);
        $this->assertSame(base64_encode('finger-bytes'), $finger['Request']['Body']['UsersFingerPrints'][0]['FingerPrints'][0]['FingerprintData']);
        $this->assertSame(0, $finger['Request']['Body']['UsersFingerPrints'][0]['FingerPrints'][0]['Type']);

        $this->postJson(route('employees.credentials.collect', $employee), ['kind' => 'card'])->assertOk();
        File::put($inbox.DIRECTORY_SEPARATOR.'GATE01__card-token.json', json_encode([
            'Response' => ['StatusCode' => '200 OK', 'Body' => ['Token' => 'card-token', 'KeepTime' => 90]],
        ]));
        $push->importInbox($device->fresh());
        File::put($inbox.DIRECTORY_SEPARATOR.'GATE01__card.json', json_encode([
            'Response' => [
                'StatusCode' => '200 OK',
                'Body' => ['Status' => 1, 'DataInfo' => [['CardNumber' => 'AB03CD12', 'Type' => 3]]],
            ],
        ]));
        $push->importInbox($device->fresh());
        $this->assertDatabaseHas('employee_credentials', [
            'employee_id' => $employee->id,
            'kind' => 'card',
            'content' => base64_encode('AB03CD12'),
        ]);
        $card = $payloads()->first(fn ($payload) => ($payload['Request']['URI'] ?? null) === '/ezapi/ac/cards');
        $this->assertNotNull($card);
        $this->assertSame('AB03CD12', $card['Request']['Body']['CardList'][0]['CardNo']);
        $this->assertSame('AT0006', $card['Request']['Body']['CardList'][0]['UserID']);
    }

    public function test_a_deleted_employee_is_not_created_again_by_the_device_sync(): void
    {
        $dir = storage_path('framework/testing/dahua-delete');
        File::deleteDirectory($dir);
        config(['dahua.push_dir' => $dir, 'dahua.register_id' => 'GATE01']);

        $org = $this->makeCompany('DEL');
        $employee = $this->makeEmployee($org, 'AT0002');
        $admin = $this->makeUser('super-admin', $org['company']);
        $device = Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Gate',
            'register_id' => 'GATE01',
            'status' => 'online',
        ]);
        \App\Models\DeviceTerminalUser::query()->create([
            'device_id' => $device->id,
            'user_id' => 'AT0002',
            'name' => 'Test AT0002',
            'employee_id' => $employee->id,
        ]);

        $this->actingAsScoped($admin)
            ->deleteJson(route('employees.destroy', $employee), [], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonPath('message', 'Employee deleted.');

        $this->assertDatabaseMissing('employees', ['id' => $employee->id, 'employee_code' => 'AT0002']);
        $this->assertDatabaseHas('device_terminal_users', [
            'device_id' => $device->id,
            'user_id' => 'AT0002',
            'employee_id' => null,
        ]);
        $this->assertNotNull(\App\Models\DeviceTerminalUser::query()->where('user_id', 'AT0002')->value('dismissed_at'));

        $inbox = $dir.DIRECTORY_SEPARATOR.'runtime'.DIRECTORY_SEPARATOR.'inbox';
        File::ensureDirectoryExists($inbox);
        File::put($inbox.DIRECTORY_SEPARATOR.'GATE01__people.json', json_encode([
            'Response' => [
                'StatusCode' => '200 OK',
                'Body' => [
                    'Total' => 1,
                    'InfoList' => [['UserID' => 'AT0002', 'UserName' => 'Test AT0002']],
                ],
            ],
        ]));
        app(\App\Services\Devices\DeviceSync::class)->run($device->fresh());

        $this->assertDatabaseMissing('employees', [
            'company_id' => $org['company']->id,
            'employee_code' => 'AT0002',
        ]);
        $pending = collect(File::files($dir.DIRECTORY_SEPARATOR.'commands'.DIRECTORY_SEPARATOR.'pending'))
            ->map(fn ($file) => json_decode(File::get($file->getPathname()), true));
        $this->assertTrue($pending->contains(fn ($payload) => ($payload['Request']['URI'] ?? null) === '/ezapi/ac/users/delete'
            && ($payload['Request']['Body']['UserIDList'][0] ?? null) === 'AT0002'));

        File::put($inbox.DIRECTORY_SEPARATOR.'GATE01__gone.json', json_encode([
            'Response' => [
                'StatusCode' => '200 OK',
                'Body' => [
                    'Total' => 1,
                    'InfoList' => [['UserID' => '100033', 'UserName' => 'Someone Else']],
                ],
            ],
        ]));
        app(\App\Services\Devices\DahuaPush::class)->importInbox($device->fresh());
        $this->assertDatabaseMissing('device_terminal_users', [
            'device_id' => $device->id,
            'user_id' => 'AT0002',
        ]);
        $this->assertDatabaseHas('employees', [
            'company_id' => $org['company']->id,
            'employee_code' => '100033',
        ]);
    }
}
