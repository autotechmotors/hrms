<?php

namespace Tests\Feature;

use App\Models\AttendancePunch;
use App\Models\Device;
use App\Services\Devices\DeviceSync;
use App\Services\Devices\HikvisionAttendance;
use App\Services\Devices\HikvisionAuthException;
use App\Services\Devices\HikvisionClient;
use App\Services\Devices\HikvisionEvents;
use App\Services\Devices\PunchForward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class HikvisionAttendanceTest extends TestCase
{
    use BuildsOrganization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        config(['hikvision.port' => null]);
    }

    public function test_a_hikvision_face_scan_is_stored_and_forwarded_like_a_device_event(): void
    {
        Http::fake(['https://hrms.example/device-events' => Http::response(['saved' => 1, 'pending' => false])]);
        config([
            'hikvision.host' => '192.168.1.156',
            'hikvision.register_id' => 'HIK1921681156',
            'hikvision.username' => 'admin',
            'hikvision.password' => 'secret',
            'devices.forward_url' => 'https://hrms.example/device-events',
            'devices.forward_token' => 'secret-token',
        ]);
        $org = $this->makeCompany('HIK');
        $employee = $this->makeEmployee($org, '900001');
        Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Gate',
            'model' => 'Hikvision',
            'register_id' => 'HIK1921681156',
            'ip_address' => '192.168.1.156',
            'status' => 'unknown',
        ]);
        $jpeg = base64_encode(base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAb/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA//2Q=='));

        $attendance = $this->attendance(function () use ($jpeg) {
            return [
                'status' => 200,
                'body' => json_encode([
                    'AcsEvent' => [
                        'numOfMatches' => 3,
                        'responseStatusStrg' => 'OK',
                        'InfoList' => [
                            [
                                'major' => 5,
                                'minor' => 75,
                                'time' => '2026-11-03T09:15:00+05:30',
                                'name' => 'New Person',
                                'employeeNoString' => '900001',
                                'serialNo' => 21,
                                'currentVerifyMode' => 'face',
                                'attendanceStatus' => 'checkIn',
                                'pictureData' => $jpeg,
                            ],
                            [
                                'major' => 5,
                                'minor' => 76,
                                'time' => '2026-11-03T09:16:00+05:30',
                                'name' => 'New Person',
                                'employeeNoString' => '900001',
                                'serialNo' => 22,
                                'currentVerifyMode' => 'face',
                            ],
                            [
                                'major' => 5,
                                'minor' => 1,
                                'time' => '2026-11-03T09:17:00+05:30',
                                'name' => 'New Person',
                                'employeeNoString' => '900001',
                                'serialNo' => 23,
                                'currentVerifyMode' => 'card',
                            ],
                        ],
                    ],
                ]),
            ];
        });

        $this->assertSame(['stored' => 1, 'held' => 0, 'added' => 0, 'paused' => false], $attendance->pull());
        $punch = AttendancePunch::query()->where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame('2026-11-03 09:15:00', $punch->punched_at->timezone('Asia/Kolkata')->format('Y-m-d H:i:s'));
        $this->assertNotNull($punch->image);
        $this->assertSame(1, AttendancePunch::query()->count());

        Http::assertSent(function ($request) {
            return $request->url() === 'https://hrms.example/device-events'
                && $request->hasHeader('X-Device-Token', 'secret-token')
                && $request['register_id'] === 'HIK1921681156'
                && $request['seq'] === '21'
                && $request['event']['Code'] === 'AccessControl'
                && $request['event']['Data']['UserID'] === '900001'
                && $request['event']['Data']['Type'] === 'Entry'
                && (int) $request['event']['Data']['ErrorCode'] === 0;
        });

        $this->assertSame(['stored' => 0, 'held' => 0, 'added' => 0, 'paused' => false], $attendance->pull());
        $this->assertSame(1, AttendancePunch::query()->count());
        Http::assertSentCount(1);
    }

    public function test_a_new_face_adds_the_employee_and_then_stores_the_punch(): void
    {
        config([
            'hikvision.host' => '192.168.1.156',
            'hikvision.register_id' => 'HIK1921681156',
            'hikvision.username' => 'admin',
            'hikvision.password' => 'secret',
            'devices.forward_url' => '',
            'devices.forward_token' => '',
        ]);
        Cache::flush();
        $org = $this->makeCompany('HIK');
        Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Gate',
            'register_id' => 'HIK1921681156',
            'ip_address' => '192.168.1.156',
            'status' => 'online',
        ]);

        $attendance = $this->attendance(function () {
            return [
                'status' => 200,
                'body' => json_encode([
                    'AcsEvent' => [
                        'numOfMatches' => 1,
                        'responseStatusStrg' => 'OK',
                        'InfoList' => [[
                            'major' => 5,
                            'minor' => 75,
                            'time' => '2026-11-03T09:15:00+05:30',
                            'name' => 'Ramesh Patel',
                            'employeeNoString' => 'AT7D149',
                            'serialNo' => 21,
                            'currentVerifyMode' => 'face',
                        ]],
                    ],
                ]),
            ];
        });

        $this->assertSame(['stored' => 1, 'held' => 0, 'added' => 1, 'paused' => false], $attendance->pull());
        $employee = \App\Models\Employee::query()->where('employee_code', 'AT7D149')->firstOrFail();
        $this->assertSame('Ramesh', $employee->first_name);
        $this->assertSame('Patel', $employee->last_name);
        $this->assertSame(1, AttendancePunch::query()->where('employee_id', $employee->id)->count());
    }

    public function test_a_person_enrolled_on_the_terminal_today_is_added_in_hrms(): void
    {
        config([
            'hikvision.host' => '192.168.1.156',
            'hikvision.register_id' => 'HIK1921681156',
            'hikvision.username' => 'admin',
            'hikvision.password' => 'secret',
            'devices.forward_url' => '',
            'devices.forward_token' => '',
        ]);
        Cache::flush();
        $org = $this->makeCompany('HIK');
        Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Gate',
            'register_id' => 'HIK1921681156',
            'ip_address' => '192.168.1.156',
            'status' => 'online',
        ]);
        $jpeg = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAb/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA//2Q==');

        $attendance = $this->attendance(function (string $method, string $url) use ($jpeg) {
            if (str_contains($url, 'UserInfo/Search')) {
                return [
                    'status' => 200,
                    'body' => json_encode([
                        'UserInfoSearch' => [
                            'responseStatusStrg' => 'OK',
                            'numOfMatches' => 1,
                            'UserInfo' => [[
                                'employeeNo' => 'Aut00741',
                                'name' => 'Darshit Goti',
                                'numOfFace' => 1,
                                'Valid' => ['beginTime' => now()->timezone('Asia/Kolkata')->toDateString().'T00:00:00'],
                                'faceURL' => 'http://192.168.1.156/enrolled.jpg',
                            ]],
                        ],
                    ]),
                ];
            }
            if ($method === 'GET') {
                return ['status' => 200, 'body' => $jpeg];
            }

            return [
                'status' => 200,
                'body' => json_encode(['AcsEvent' => ['numOfMatches' => 0, 'responseStatusStrg' => 'OK', 'InfoList' => []]]),
            ];
        });

        $this->assertSame(1, $attendance->pull()['added']);
        $employee = \App\Models\Employee::query()->where('employee_code', 'Aut00741')->firstOrFail();
        $this->assertSame('Darshit', $employee->first_name);
        $this->assertSame('Goti', $employee->last_name);
        $this->assertNotNull($employee->photograph_path);
        $this->assertDatabaseHas('device_terminal_users', [
            'user_id' => 'Aut00741',
            'employee_id' => $employee->id,
        ]);
    }

    public function test_an_unchanged_terminal_user_count_skips_the_full_user_list(): void
    {
        config([
            'hikvision.host' => '192.168.1.156',
            'hikvision.register_id' => 'HIK1921681156',
            'hikvision.username' => 'admin',
            'hikvision.password' => 'secret',
            'devices.forward_url' => '',
            'devices.forward_token' => '',
        ]);
        Cache::flush();
        $org = $this->makeCompany('HIK');
        Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Gate',
            'register_id' => 'HIK1921681156',
            'ip_address' => '192.168.1.156',
            'status' => 'online',
        ]);
        $searches = 0;
        $attendance = $this->attendance(function (string $method, string $url) use (&$searches) {
            if (str_contains($url, 'UserInfo/Count')) {
                return ['status' => 200, 'body' => json_encode(['UserInfoCount' => ['userNumber' => 1]])];
            }
            if (str_contains($url, 'UserInfo/Search')) {
                $searches++;

                return [
                    'status' => 200,
                    'body' => json_encode([
                        'UserInfoSearch' => [
                            'responseStatusStrg' => 'OK',
                            'numOfMatches' => 1,
                            'UserInfo' => [[
                                'employeeNo' => 'Aut00741',
                                'name' => 'Darshit Goti',
                                'Valid' => ['beginTime' => now()->timezone('Asia/Kolkata')->toDateString().'T00:00:00'],
                            ]],
                        ],
                    ]),
                ];
            }

            return [
                'status' => 200,
                'body' => json_encode(['AcsEvent' => ['numOfMatches' => 0, 'responseStatusStrg' => 'OK', 'InfoList' => []]]),
            ];
        });

        $this->assertSame(1, $attendance->pull()['added']);
        $this->assertSame(0, $attendance->pull()['added']);
        $this->assertSame(1, $searches);
    }

    public function test_a_face_button_captures_on_the_hikvision_terminal_and_stores_it(): void
    {
        config([
            'hikvision.username' => 'admin',
            'hikvision.password' => 'secret',
        ]);
        $org = $this->makeCompany('HIK');
        $employee = $this->makeEmployee($org, 'AT0007');
        Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Gate',
            'register_id' => 'HIK1921681156',
            'ip_address' => '192.168.1.156',
            'status' => 'online',
        ]);
        $jpeg = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAb/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA//2Q==');
        $urls = [];
        $capture = null;
        $this->app->instance(HikvisionClient::class, new HikvisionClient(function (string $method, string $url, array|string|null $body = null) use (&$urls, &$capture, $jpeg) {
            $urls[] = $method.' '.$url;
            if (str_contains($url, 'CaptureFaceData')) {
                $capture = $body;

                return ['status' => 200, 'body' => json_encode(['CaptureFaceData' => ['faceDataUrl' => 'http://192.168.1.156/face.jpg', 'captureProgress' => 100]])];
            }
            if ($method === 'GET') {
                return ['status' => 200, 'body' => $jpeg];
            }

            return ['status' => 200, 'body' => json_encode(['statusCode' => 1, 'statusString' => 'OK'])];
        }));

        $result = app(\App\Services\Devices\EmployeePush::class)->collect($employee, 'face');

        $this->assertSame(1, $result['devices']);
        $this->assertSame('Face saved on Gate.', $result['message']);
        $this->assertDatabaseHas('employee_credentials', ['employee_id' => $employee->id, 'kind' => 'face']);
        $this->assertTrue(collect($urls)->contains(fn (string $url) => str_contains($url, 'UserInfo/Record')));
        $this->assertTrue(collect($urls)->contains(fn (string $url) => str_contains($url, 'CaptureFaceData') && ! str_contains($url, 'format=json')));
        $this->assertIsString($capture);
        $this->assertStringContainsString('<CaptureFaceDataCond', $capture);
        $this->assertStringContainsString('<dataType>url</dataType>', $capture);
        $this->assertTrue(collect($urls)->contains(fn (string $url) => str_contains($url, 'FaceDataRecord')));
    }

    public function test_an_out_terminal_keeps_a_raw_photo_when_the_url_capture_is_empty(): void
    {
        config([
            'hikvision.username' => 'admin',
            'hikvision.password' => 'secret',
        ]);
        $org = $this->makeCompany('HIK');
        $employee = $this->makeEmployee($org, 'AT0011');
        $device = Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Hikvision-(OUT)',
            'register_id' => 'HIK1921681160',
            'ip_address' => '192.168.1.160',
            'punch_role' => 'out',
            'status' => 'online',
        ]);
        $jpeg = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAb/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA//2Q==');
        $captures = 0;
        $this->app->instance(HikvisionClient::class, new HikvisionClient(function (string $method, string $url) use (&$captures, $jpeg) {
            if (str_contains($url, 'CaptureFaceData') && ! str_contains($url, 'capabilities')) {
                $captures++;

                return $captures === 1
                    ? ['status' => 200, 'body' => json_encode(['statusCode' => 1, 'CaptureFaceData' => ['captureProgress' => 0]])]
                    : ['status' => 200, 'body' => $jpeg];
            }

            return ['status' => 200, 'body' => json_encode(['statusCode' => 1, 'statusString' => 'OK'])];
        }));

        $result = app(\App\Services\Devices\EmployeePush::class)->collect($employee, 'face', null, $device);

        $this->assertSame(1, $result['devices']);
        $this->assertSame(2, $captures);
        $this->assertDatabaseHas('employee_credentials', ['employee_id' => $employee->id, 'kind' => 'face']);
    }

    public function test_a_face_button_opens_the_camera_without_rewriting_an_existing_person(): void
    {
        config([
            'hikvision.username' => 'admin',
            'hikvision.password' => 'secret',
        ]);
        $org = $this->makeCompany('HIK');
        $employee = $this->makeEmployee($org, 'AT0010');
        $device = Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Gate',
            'register_id' => 'HIK1921681156',
            'ip_address' => '192.168.1.156',
            'status' => 'online',
        ]);
        \App\Models\DeviceTerminalUser::query()->create([
            'device_id' => $device->id,
            'user_id' => 'AT0010',
            'name' => 'Neha Bonsal',
            'employee_id' => $employee->id,
        ]);
        $jpeg = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAb/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA//2Q==');
        $urls = [];
        $this->app->instance(HikvisionClient::class, new HikvisionClient(function (string $method, string $url) use (&$urls, $jpeg) {
            $urls[] = $method.' '.$url;
            if (str_contains($url, 'CaptureFaceData')) {
                return ['status' => 200, 'body' => json_encode(['CaptureFaceData' => ['faceDataUrl' => 'http://192.168.1.156/face.jpg', 'captureProgress' => 100]])];
            }
            if ($method === 'GET') {
                return ['status' => 200, 'body' => $jpeg];
            }

            return ['status' => 200, 'body' => json_encode(['statusCode' => 1, 'statusString' => 'OK'])];
        }));

        app(\App\Services\Devices\EmployeePush::class)->collect($employee, 'face');

        $this->assertStringContainsString('CaptureFaceData', $urls[0]);
        $this->assertFalse(collect($urls)->contains(fn (string $url) => str_contains($url, 'UserInfo')));
    }

    public function test_saving_an_employee_adds_that_person_on_the_hikvision_terminal(): void
    {
        config([
            'hikvision.username' => 'admin',
            'hikvision.password' => 'secret',
        ]);
        $org = $this->makeCompany('HIK');
        $employee = $this->makeEmployee($org, 'AT0099');
        Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Gate',
            'register_id' => 'HIK1921681156',
            'ip_address' => '192.168.1.156',
            'status' => 'online',
        ]);
        $records = 0;
        $this->app->instance(HikvisionClient::class, new HikvisionClient(function (string $method, string $url) use (&$records) {
            if (str_contains($url, 'UserInfo/Record')) {
                $records++;
            }

            return ['status' => 200, 'body' => json_encode(['statusCode' => 1, 'statusString' => 'OK'])];
        }));

        $push = app(\App\Services\Devices\EmployeePush::class);
        $this->assertSame(0, $push->sync($employee));
        $this->assertSame(0, $records);
        $this->assertSame(1, $push->saveToTerminals($employee));
        $this->assertSame(1, $records);
        $this->assertDatabaseHas('device_terminal_users', [
            'user_id' => 'AT0099',
            'employee_id' => $employee->id,
        ]);
    }

    public function test_devices_sync_keeps_hikvision_on_isapi(): void
    {
        $org = $this->makeCompany('HIK');
        $device = Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Face',
            'register_id' => 'HIK1921681156',
            'ip_address' => '192.168.1.156',
            'status' => 'online',
        ]);

        $this->assertSame(
            'Face: Hikvision people 0',
            app(DeviceSync::class)->run($device),
        );
    }

    public function test_a_refused_hikvision_login_is_not_retried_immediately(): void
    {
        config([
            'hikvision.host' => '192.168.1.156',
            'hikvision.register_id' => 'HIK1921681156',
            'hikvision.username' => 'admin',
            'hikvision.password' => 'wrong',
            'devices.forward_url' => '',
            'devices.forward_token' => '',
        ]);
        Cache::flush();
        $calls = 0;
        $attendance = $this->attendance(function () use (&$calls) {
            $calls++;

            return ['status' => 401, 'body' => ''];
        });

        try {
            $attendance->pull();
            $this->fail('A refused login should stop the pull.');
        } catch (HikvisionAuthException) {
            $this->assertSame(2, $calls);
        }

        $this->assertSame(['stored' => 0, 'held' => 0, 'added' => 0, 'paused' => true], $attendance->pull());
        $this->assertSame(2, $calls);
    }

    public function test_a_face_scan_posted_by_the_terminal_is_stored_on_this_server(): void
    {
        config(['devices.forward_token' => 'secrettoken', 'hikvision.register_id' => 'HIK1921681156']);
        $org = $this->makeCompany('HIK');
        $employee = $this->makeEmployee($org, '900001');
        Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Gate',
            'register_id' => 'HIK1921681156',
            'ip_address' => '192.168.1.156',
            'status' => 'online',
        ]);
        $jpeg = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAb/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA//2Q==');
        $json = json_encode([
            'ipAddress' => '192.168.1.156',
            'dateTime' => '2026-11-03T13:24:05+05:30',
            'eventType' => 'AccessControllerEvent',
            'AccessControllerEvent' => [
                'majorEventType' => 5,
                'subEventType' => 75,
                'employeeNoString' => '900001',
                'name' => 'New Person',
                'serialNo' => 88,
                'currentVerifyMode' => 'face',
                'attendanceStatus' => 'checkIn',
            ],
        ]);
        $boundary = '----HikBound';
        $body = "--{$boundary}\r\nContent-Disposition: form-data; name=\"event_log\"\r\nContent-Type: application/json\r\n\r\n{$json}\r\n--{$boundary}\r\nContent-Disposition: form-data; name=\"Picture\"; filename=\"Picture.jpg\"\r\nContent-Type: image/jpeg\r\n\r\n{$jpeg}\r\n--{$boundary}--\r\n";

        $this->call('POST', '/hikvision-events/secrettoken', [], [], [], [
            'CONTENT_TYPE' => 'multipart/form-data; boundary='.$boundary,
        ], $body)
            ->assertOk()
            ->assertJson(['saved' => 1, 'pending' => false]);

        $punch = AttendancePunch::query()->first();
        $this->assertNotNull($punch);
        $this->assertSame($employee->id, $punch->employee_id);
        $this->assertSame('in', $punch->direction);
        $this->assertSame('2026-11-03 13:24:05', $punch->punched_at->timezone('Asia/Kolkata')->format('Y-m-d H:i:s'));
        $this->assertNotNull($punch->image);

        $this->call('POST', '/hikvision-events/secrettoken', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['eventType' => 'heartBeat', 'dateTime' => '2026-11-03T13:25:00+05:30']))
            ->assertOk()
            ->assertJson(['saved' => 0, 'ignored' => true]);

        $this->call('POST', '/hikvision-events/secrettoken', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'dateTime' => '2026-11-03T13:26:00+05:30',
            'eventType' => 'AccessControllerEvent',
            'AccessControllerEvent' => [
                'subEventType' => 75,
                'employeeNoString' => 'missing-person',
                'name' => 'Nobody',
                'serialNo' => 89,
                'currentVerifyMode' => 'face',
            ],
        ]))
            ->assertOk()
            ->assertJson(['saved' => 0, 'pending' => true]);

        $this->assertSame(1, AttendancePunch::query()->count());
        $this->postJson('/hikvision-events/wrongtoken', [])->assertUnauthorized();
        $this->get('/hikvision-events/secrettoken')->assertOk()->assertSee('Hikvision event receiver is running.');
    }

    public function test_a_face_scan_keeps_the_punch_and_drops_the_selfie_when_storage_is_off(): void
    {
        config(['devices.forward_token' => 'secrettoken', 'hikvision.register_id' => 'HIK1921681156']);
        $org = $this->makeCompany('HIK');
        $employee = $this->makeEmployee($org, '900001');
        \App\Models\AttendanceRule::query()->create([
            'company_id' => $org['company']->id,
            'grace_minutes' => 0,
            'mark_absent_without_punch' => true,
            'store_selfie' => false,
        ]);
        Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Gate',
            'register_id' => 'HIK1921681156',
            'ip_address' => '192.168.1.156',
            'status' => 'online',
        ]);
        $jpeg = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAb/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA//2Q==');
        $json = json_encode([
            'ipAddress' => '192.168.1.156',
            'dateTime' => '2026-11-03T13:24:05+05:30',
            'eventType' => 'AccessControllerEvent',
            'AccessControllerEvent' => [
                'majorEventType' => 5,
                'subEventType' => 75,
                'employeeNoString' => '900001',
                'name' => 'New Person',
                'serialNo' => 88,
                'currentVerifyMode' => 'face',
                'attendanceStatus' => 'checkIn',
            ],
        ]);
        $boundary = '----HikBound';
        $body = "--{$boundary}\r\nContent-Disposition: form-data; name=\"event_log\"\r\nContent-Type: application/json\r\n\r\n{$json}\r\n--{$boundary}\r\nContent-Disposition: form-data; name=\"Picture\"; filename=\"Picture.jpg\"\r\nContent-Type: image/jpeg\r\n\r\n{$jpeg}\r\n--{$boundary}--\r\n";

        $this->call('POST', '/hikvision-events/secrettoken', [], [], [], [
            'CONTENT_TYPE' => 'multipart/form-data; boundary='.$boundary,
        ], $body)
            ->assertOk()
            ->assertJson(['saved' => 1, 'pending' => false]);

        $punch = AttendancePunch::query()->where('employee_id', $employee->id)->first();
        $this->assertNotNull($punch);
        $this->assertNull($punch->image);
    }

    public function test_a_terminal_directory_is_added_on_this_server_without_passwords(): void
    {
        config(['devices.forward_token' => 'secrettoken', 'hikvision.register_id' => 'HIK1921681156']);
        $org = $this->makeCompany('HIK');
        $this->makeEmployee($org, '900001');
        Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Gate',
            'register_id' => 'HIK1921681156',
            'ip_address' => '192.168.1.156',
            'status' => 'online',
        ]);

        $this->postJson('/hikvision-events/secrettoken/people', [
            'people' => [
                ['code' => '900001', 'name' => 'New Person'],
                ['code' => '900002', 'name' => 'Second Person'],
            ],
        ])->assertOk()->assertJson(['added' => 1, 'existing' => 1]);

        $this->assertDatabaseHas('employees', [
            'employee_code' => '900002',
            'first_name' => 'Second',
            'last_name' => 'Person',
        ]);
        $this->postJson('/hikvision-events/wrongtoken/people', ['people' => [['code' => '1', 'name' => 'A']]])
            ->assertUnauthorized();
    }

    public function test_a_published_port_reaches_the_terminal_without_the_office_pc(): void
    {
        config([
            'hikvision.host' => '103.214.226.146',
            'hikvision.port' => 18080,
            'hikvision.username' => 'admin',
            'hikvision.password' => 'secret',
            'hikvision.direct' => false,
        ]);
        $org = $this->makeCompany('HIK');
        $employee = $this->makeEmployee($org, 'AT0099');
        Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Gate',
            'register_id' => 'HIK1921681156',
            'ip_address' => '192.168.1.156',
            'status' => 'online',
        ]);
        $urls = [];
        $this->app->instance(HikvisionClient::class, new HikvisionClient(function (string $method, string $url) use (&$urls) {
            $urls[] = $url;

            return ['status' => 200, 'body' => json_encode(['statusCode' => 1, 'statusString' => 'OK'])];
        }));
        $jobs = \Mockery::mock(\App\Services\Devices\HikvisionJobs::class);
        $jobs->shouldReceive('dispatchAndWait')->never();
        $this->app->instance(\App\Services\Devices\HikvisionJobs::class, $jobs);

        $this->assertSame(1, app(\App\Services\Devices\EmployeePush::class)->saveToTerminals($employee));
        $this->assertTrue(collect($urls)->contains(fn (string $url) => str_starts_with($url, 'http://103.214.226.146:18080/')));
        $this->assertFalse(collect($urls)->contains(fn (string $url) => str_contains($url, '192.168.1.156')));
    }

    public function test_a_private_office_address_is_dialed_through_the_open_firewall_port(): void
    {
        config([
            'hikvision.public_host' => '103.214.226.146',
            'hikvision.host' => '192.168.1.156',
            'hikvision.port' => 18080,
            'hikvision.terminals.out.port' => 18081,
        ]);
        $in = new Device(['ip_address' => '192.168.1.156', 'port' => 80, 'punch_role' => 'in']);
        $out = new Device(['ip_address' => '192.168.1.160', 'port' => 80, 'punch_role' => 'out']);

        $this->assertSame('103.214.226.146:18080', $in->terminalHost());
        $this->assertSame('103.214.226.146:18081', $out->terminalHost());
    }

    public function test_saving_an_employee_asks_the_office_link_when_the_server_cannot_see_the_terminal(): void
    {
        config([
            'hikvision.password' => '',
            'hikvision.direct' => false,
            'hikvision.register_id' => 'HIK1921681156',
        ]);
        $org = $this->makeCompany('HIK');
        $employee = $this->makeEmployee($org, 'AT0099');
        Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Gate',
            'register_id' => 'HIK1921681156',
            'ip_address' => '192.168.1.156',
            'status' => 'online',
        ]);
        $jobs = \Mockery::mock(\App\Services\Devices\HikvisionJobs::class);
        $jobs->shouldReceive('dispatchAndWait')->once()->withArgs(function (array $job): bool {
            return $job['kind'] === 'profile' && $job['code'] === 'AT0099';
        })->andReturn(['status' => 'done', 'message' => '']);
        $this->app->instance(\App\Services\Devices\HikvisionJobs::class, $jobs);

        $this->assertSame(1, app(\App\Services\Devices\EmployeePush::class)->saveToTerminals($employee));
    }

    public function test_missing_faces_and_fingers_are_copied_from_the_terminal_without_passwords(): void
    {
        config([
            'hikvision.host' => '192.168.1.156',
            'hikvision.register_id' => 'HIK1921681156',
            'hikvision.username' => 'admin',
            'hikvision.password' => 'secret',
        ]);
        $org = $this->makeCompany('HIK');
        $employee = $this->makeEmployee($org, 'AUTO002');
        Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Gate',
            'register_id' => 'HIK1921681156',
            'ip_address' => '192.168.1.156',
            'status' => 'online',
        ]);
        $jpeg = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAb/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA//2Q==');
        $this->app->instance(HikvisionClient::class, new HikvisionClient(function (string $method, string $url) use ($jpeg) {
            if (str_contains($url, 'UserInfo/Search')) {
                return ['status' => 200, 'body' => json_encode([
                    'UserInfoSearch' => [
                        'responseStatusStrg' => 'OK',
                        'numOfMatches' => 1,
                        'UserInfo' => [[
                            'employeeNo' => 'AUTO002',
                            'name' => 'Auto User',
                            'password' => 'do-not-store',
                            'numOfFace' => 1,
                            'numOfFP' => 1,
                        ]],
                    ],
                ])];
            }
            if (str_contains($url, 'FDSearch')) {
                return ['status' => 200, 'body' => json_encode([
                    'statusCode' => 1,
                    'numOfMatches' => 1,
                    'MatchList' => [[
                        'FPID' => 'AUTO002',
                        'faceURL' => 'http://192.168.1.156/enrolled.jpg',
                    ]],
                ])];
            }
            if (str_contains($url, 'FingerPrintUpload')) {
                return ['status' => 200, 'body' => json_encode([
                    'FingerPrintInfo' => [
                        'status' => 'OK',
                        'FingerPrintList' => [[
                            'fingerPrintID' => 1,
                            'fingerData' => base64_encode('finger-template'),
                        ]],
                    ],
                ])];
            }
            if ($method === 'GET') {
                return ['status' => 200, 'body' => $jpeg];
            }

            return ['status' => 200, 'body' => ''];
        }));

        $this->artisan('hikvision:credentials')->assertSuccessful();

        $this->assertDatabaseHas('employee_credentials', ['employee_id' => $employee->id, 'kind' => 'face', 'slot' => '0']);
        $this->assertDatabaseHas('employee_credentials', ['employee_id' => $employee->id, 'kind' => 'finger', 'slot' => '1']);
        $this->assertDatabaseMissing('employee_credentials', ['employee_id' => $employee->id, 'kind' => 'password']);
    }

    public function test_people_who_exist_only_on_the_terminal_are_added_before_their_credentials_are_copied(): void
    {
        config([
            'hikvision.host' => '192.168.1.156',
            'hikvision.register_id' => 'HIK1921681156',
            'hikvision.username' => 'admin',
            'hikvision.password' => 'secret',
        ]);
        $org = $this->makeCompany('HIK');
        Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Gate',
            'register_id' => 'HIK1921681156',
            'ip_address' => '192.168.1.156',
            'status' => 'online',
        ]);
        $jpeg = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAb/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA//2Q==');
        $this->app->instance(HikvisionClient::class, new HikvisionClient(function (string $method, string $url) use ($jpeg) {
            if (str_contains($url, 'UserInfo/Search')) {
                return ['status' => 200, 'body' => json_encode([
                    'UserInfoSearch' => [
                        'responseStatusStrg' => 'OK',
                        'numOfMatches' => 1,
                        'UserInfo' => [[
                            'employeeNo' => 'AUTO009',
                            'name' => 'Ravi Patel',
                            'password' => 'do-not-store',
                            'numOfFace' => 1,
                            'numOfFP' => 0,
                        ]],
                    ],
                ])];
            }
            if (str_contains($url, 'FDSearch')) {
                return ['status' => 200, 'body' => json_encode([
                    'statusCode' => 1,
                    'numOfMatches' => 1,
                    'MatchList' => [[
                        'FPID' => 'AUTO009',
                        'faceURL' => 'http://192.168.1.156/enrolled.jpg',
                    ]],
                ])];
            }
            if ($method === 'GET') {
                return ['status' => 200, 'body' => $jpeg];
            }

            return ['status' => 200, 'body' => ''];
        }));

        $this->artisan('hikvision:credentials')
            ->expectsOutputToContain('Employees added: 1.')
            ->assertSuccessful();

        $employee = \App\Models\Employee::query()->where('employee_code', 'AUTO009')->first();
        $this->assertNotNull($employee);
        $this->assertSame('Ravi', $employee->first_name);
        $this->assertSame('Patel', $employee->last_name);
        $this->assertDatabaseHas('employee_credentials', ['employee_id' => $employee->id, 'kind' => 'face']);
        $this->assertDatabaseMissing('employee_credentials', ['employee_id' => $employee->id, 'kind' => 'password']);
        $this->assertDatabaseHas('device_terminal_users', ['user_id' => 'AUTO009', 'employee_id' => $employee->id]);
    }

    public function test_a_saved_device_address_is_used_instead_of_the_env_host(): void
    {
        config([
            'hikvision.host' => '192.168.1.156',
            'hikvision.port' => null,
            'hikvision.username' => 'admin',
            'hikvision.password' => 'secret',
        ]);
        $org = $this->makeCompany('HIK');
        $employee = $this->makeEmployee($org, 'AT0010');
        $device = Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Gate',
            'register_id' => 'HIK1921681156',
            'ip_address' => '10.1.1.5',
            'port' => 90,
            'username' => 'admin',
            'password' => 'from-settings',
            'status' => 'online',
        ]);
        \App\Models\DeviceTerminalUser::query()->create([
            'device_id' => $device->id,
            'user_id' => 'AT0010',
            'name' => 'Neha Bonsal',
            'employee_id' => $employee->id,
        ]);
        $jpeg = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAb/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA//2Q==');
        $urls = [];
        $this->app->instance(HikvisionClient::class, new HikvisionClient(function (string $method, string $url) use (&$urls, $jpeg) {
            $urls[] = $url;
            if (str_contains($url, 'CaptureFaceData')) {
                return ['status' => 200, 'body' => json_encode(['CaptureFaceData' => ['faceDataUrl' => 'http://10.1.1.5/face.jpg']])];
            }
            if ($method === 'GET') {
                return ['status' => 200, 'body' => $jpeg];
            }

            return ['status' => 200, 'body' => json_encode(['statusCode' => 1])];
        }));

        app(\App\Services\Devices\EmployeePush::class)->collect($employee, 'face');

        $this->assertStringStartsWith('http://10.1.1.5:90/', $urls[0]);
    }

    public function test_removing_a_finger_deletes_it_on_the_terminal_and_in_laravel(): void
    {
        config([
            'hikvision.host' => '192.168.1.156',
            'hikvision.username' => 'admin',
            'hikvision.password' => 'secret',
        ]);
        $org = $this->makeCompany('HIK');
        $employee = $this->makeEmployee($org, 'AT0010');
        Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Gate',
            'register_id' => 'HIK1921681156',
            'ip_address' => '192.168.1.156',
            'status' => 'online',
        ]);
        $credential = \App\Models\EmployeeCredential::query()->create([
            'employee_id' => $employee->id,
            'kind' => 'finger',
            'slot' => '2',
            'content' => base64_encode('finger-template'),
        ]);
        $urls = [];
        $this->app->instance(HikvisionClient::class, new HikvisionClient(function (string $method, string $url) use (&$urls) {
            $urls[] = $method.' '.$url;

            return ['status' => 200, 'body' => json_encode(['statusCode' => 1, 'statusString' => 'OK'])];
        }));

        app(\App\Services\Devices\EmployeePush::class)->removeCredential($employee, $credential);

        $this->assertTrue(collect($urls)->contains(fn (string $url) => str_contains($url, 'FingerPrint/Delete')));
        $this->assertDatabaseMissing('employee_credentials', ['id' => $credential->id]);
    }

    public function test_the_pull_command_asks_for_the_terminal_password(): void
    {
        config(['hikvision.password' => '']);

        $this->artisan('hikvision:pull')->assertFailed();
    }

    public function test_in_and_out_terminals_keep_their_own_scans_and_an_enrollment_terminal_does_not(): void
    {
        config(['devices.forward_token' => 'secrettoken']);
        $org = $this->makeCompany('HIK');
        $employee = $this->makeEmployee($org, '900001');
        Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Door IN',
            'register_id' => 'HIKIN',
            'ip_address' => '103.214.226.146',
            'lan_ip' => '192.168.1.156',
            'port' => 18080,
            'punch_role' => 'in',
            'status' => 'online',
        ]);
        $out = Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Door OUT',
            'register_id' => 'HIKOUT',
            'ip_address' => '103.214.226.146',
            'lan_ip' => '192.168.1.200',
            'port' => 18081,
            'punch_role' => 'out',
            'status' => 'online',
        ]);
        Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Enrollment',
            'register_id' => 'HIKENROLL',
            'ip_address' => '192.168.1.210',
            'punch_role' => 'enroll',
            'status' => 'online',
        ]);

        $this->postScan('192.168.1.156', '2026-11-25T09:05:00+05:30', 1);
        $this->postScan('192.168.1.200', '2026-11-25T18:10:00+05:30', 2);
        $this->postScan('192.168.1.210', '2026-11-25T18:11:00+05:30', 3)
            ->assertJson(['saved' => 0, 'pending' => false]);

        $punches = AttendancePunch::query()->where('employee_id', $employee->id)->orderBy('punched_at')->get();
        $this->assertCount(2, $punches);
        $this->assertSame('in', $punches[0]->direction);
        $this->assertSame('out', $punches[1]->direction);
        $this->assertSame($out->id, $punches[1]->device_id);

        app(DeviceSync::class)->rebuild($employee, \Carbon\Carbon::parse('2026-11-25', 'Asia/Kolkata'));
        $day = \App\Models\AttendanceDay::query()->where('employee_id', $employee->id)->whereDate('work_date', '2026-11-25')->first();
        $this->assertSame('09:05:00', substr((string) $day->punch_in, 0, 8));
        $this->assertSame('18:10:00', substr((string) $day->punch_out, 0, 8));
    }

    private function postScan(string $ip, string $at, int $serial): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/hikvision-events/secrettoken', [
            'ipAddress' => $ip,
            'dateTime' => $at,
            'eventType' => 'AccessControllerEvent',
            'AccessControllerEvent' => [
                'subEventType' => 75,
                'employeeNoString' => '900001',
                'name' => 'New Person',
                'serialNo' => $serial,
                'currentVerifyMode' => 'face',
                'attendanceStatus' => 'checkIn',
            ],
        ]);
    }

    private function attendance(callable $request): HikvisionAttendance
    {
        return new HikvisionAttendance(
            new HikvisionEvents(new HikvisionClient($request)),
            app(DeviceSync::class),
            app(PunchForward::class),
            app(\App\Services\Devices\DevicePeople::class),
        );
    }
}
