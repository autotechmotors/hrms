<?php

namespace Tests\Feature;

use App\Models\Device;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class DeviceAttendanceTest extends TestCase
{
    use BuildsOrganization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
    }

    public function test_device_people_and_punches_land_in_hrms(): void
    {
        $dir = storage_path('framework/testing/dahua-attendance');
        File::deleteDirectory($dir);
        config([
            'dahua.push_dir' => $dir,
            'dahua.register_id' => 'GATE01',
        ]);

        $org = $this->makeCompany('ATT');
        $hr = $this->makeUser('hr-admin', $org['company']);
        $device = Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Gate',
            'register_id' => 'GATE01',
            'status' => 'online',
        ]);

        $jpeg = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAb/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA//2Q==');
        $this->assertNotFalse($jpeg);

        $inbox = $dir.DIRECTORY_SEPARATOR.'runtime'.DIRECTORY_SEPARATOR.'inbox';
        File::ensureDirectoryExists($inbox);
        File::put($inbox.DIRECTORY_SEPARATOR.'GATE01__people.json', json_encode([
            'Response' => [
                'StatusCode' => '200 OK',
                'Body' => [
                    'InfoList' => [[
                        'UserID' => '900001',
                        'UserName' => 'New Person',
                        'PhotoData' => [base64_encode($jpeg)],
                    ]],
                ],
            ],
        ]));

        $saved = $dir.DIRECTORY_SEPARATOR.'saved_files';
        File::ensureDirectoryExists($saved);
        $inAt = Carbon::parse('2026-10-20 09:15:00', 'Asia/Kolkata');
        File::put($saved.DIRECTORY_SEPARATOR.'21.json', json_encode([
            'DataSeq' => '21',
            'RegisterID' => 'GATE01',
            'NotifyInfo' => [
                'Events' => [[
                    'Code' => 'AccessControl',
                    'Data' => [
                        'ErrorCode' => 0,
                        'Type' => 'Entry',
                        'RealUTC' => $inAt->utc()->timestamp,
                        'UserID' => '900001',
                        'UserName' => 'New Person',
                        'SN' => 'GATE01',
                    ],
                ]],
            ],
        ]));
        File::put($saved.DIRECTORY_SEPARATOR.'21_Pic.jpg', $jpeg);

        $this->artisan('devices:sync')->assertSuccessful();

        $this->assertDatabaseHas('employees', [
            'company_id' => $org['company']->id,
            'employee_code' => '900001',
            'first_name' => 'New',
            'last_name' => 'Person',
        ]);
        $this->assertDatabaseHas('employee_credentials', [
            'kind' => 'face',
        ]);
        $profile = \App\Models\Employee::query()->where('employee_code', '900001')->firstOrFail();
        $this->assertNotNull($profile->photograph_path);
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('local')->exists($profile->photograph_path));
        $this->assertDatabaseHas('attendance_punches', [
            'device_id' => $device->id,
            'direction' => 'in',
        ]);

        $employee = \App\Models\Employee::query()->where('employee_code', '900001')->firstOrFail();
        $punch = \App\Models\AttendancePunch::query()->where('employee_id', $employee->id)->firstOrFail();
        $this->actingAsScoped($hr)
            ->get(route('attendance.show', ['employee' => $employee, 'date' => '2026-10-20']))
            ->assertOk()
            ->assertSee('09:15 AM')
            ->assertSee('Attendance record')
            ->assertSee('Entry')
            ->assertSee(route('attendance.punch.photo', $punch), false);

        $face = \App\Models\EmployeeCredential::query()->where('employee_id', $employee->id)->where('kind', 'face')->firstOrFail();
        $this->get(route('attendance.index', ['date' => '2026-10-20']))
            ->assertOk()
            ->assertSee('View photo')
            ->assertSee(route('attendance.credential.photo', $face), false);

        $this->get(route('attendance.punch.photo', $punch))
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');

        $this->getJson(route('attendance.employee.feed', ['employee' => $employee, 'month' => '2026-10']))
            ->assertOk()
            ->assertJsonPath('days.0.in', '09:15 AM')
            ->assertJsonPath('days.0.in_note', 'Door photo');
    }

    public function test_entry_without_a_door_photo_shows_the_enrolled_face(): void
    {
        $org = $this->makeCompany('FACE');
        $hr = $this->makeUser('hr-admin', $org['company']);
        $employee = $this->makeEmployee($org, '100033');
        $jpeg = base64_encode(base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAb/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA//2Q=='));
        $face = \App\Models\EmployeeCredential::query()->create([
            'employee_id' => $employee->id,
            'kind' => 'face',
            'slot' => '0',
            'content' => $jpeg,
        ]);
        \App\Models\AttendancePunch::query()->create([
            'employee_id' => $employee->id,
            'punched_at' => '2026-10-20 10:50:21',
            'direction' => 'in',
            'external_key' => 'face-fallback',
        ]);
        \App\Models\AttendanceDay::query()->create([
            'employee_id' => $employee->id,
            'work_date' => '2026-10-20',
            'punch_in' => '10:50:21',
            'status' => 'absent',
            'source' => 'device',
        ]);

        $this->actingAsScoped($hr)
            ->getJson(route('attendance.employee.feed', ['employee' => $employee, 'month' => '2026-10']))
            ->assertOk()
            ->assertJsonPath('days.0.in', '10:50 AM')
            ->assertJsonPath('days.0.in_note', 'Enrolled face')
            ->assertJsonPath('days.0.in_photo', route('attendance.credential.photo', $face))
            ->assertJsonMissingPath('days.0.out_photo')
            ->assertJsonMissingPath('days.0.punches.0.photo');

        $this->get(route('attendance.show', ['employee' => $employee, 'date' => '2026-10-20']))
            ->assertOk()
            ->assertSee('10:50 AM')
            ->assertSee('Tue, 20 Oct')
            ->assertDontSee('10:50:21');
    }

    public function test_repeat_face_scans_pair_into_one_clock_in_and_clock_out(): void
    {
        $org = $this->makeCompany('PAIR');
        $hr = $this->makeUser('hr-admin', $org['company']);
        $employee = $this->makeEmployee($org, '100033');
        foreach (['10:50:21', '10:50:26', '11:28:34'] as $time) {
            \App\Models\AttendancePunch::query()->create([
                'employee_id' => $employee->id,
                'punched_at' => '2026-10-20 '.$time,
                'direction' => 'in',
                'external_key' => 'pair-'.$time,
            ]);
        }

        $this->actingAsScoped($hr)
            ->getJson(route('attendance.employee.feed', ['employee' => $employee, 'month' => '2026-10']))
            ->assertOk()
            ->assertJsonPath('days.0.in', '10:50 AM')
            ->assertJsonPath('days.0.out', '11:28 AM')
            ->assertJsonCount(2, 'days.0.punches')
            ->assertJsonPath('days.0.punches.0.direction', 'In')
            ->assertJsonPath('days.0.punches.1.direction', 'Out')
            ->assertJsonPath('days.0.worked', '38m')
            ->assertJsonPath('days.0.status', 'Present');
    }

    public function test_one_face_stays_clock_in_and_a_later_return_does_not_erase_clock_out(): void
    {
        $org = $this->makeCompany('DAY');
        $hr = $this->makeUser('hr-admin', $org['company']);
        $alone = $this->makeEmployee($org, 'AT0001');
        \App\Models\AttendancePunch::query()->create([
            'employee_id' => $alone->id,
            'punched_at' => '2026-10-20 08:15:00',
            'direction' => 'in',
            'external_key' => 'alone',
        ]);
        $returned = $this->makeEmployee($org, 'AT0006');
        foreach (['09:00:00', '09:10:00', '09:30:00', '09:40:00'] as $time) {
            \App\Models\AttendancePunch::query()->create([
                'employee_id' => $returned->id,
                'punched_at' => '2026-10-20 '.$time,
                'direction' => 'in',
                'external_key' => 'return-'.$time,
            ]);
        }

        $this->actingAsScoped($hr)
            ->getJson(route('attendance.employee.feed', ['employee' => $alone, 'month' => '2026-10']))
            ->assertOk()
            ->assertJsonPath('days.0.in', '08:15 AM')
            ->assertJsonPath('days.0.out', null)
            ->assertJsonPath('days.0.status', 'Present')
            ->assertJsonCount(1, 'days.0.punches');

        $this->getJson(route('attendance.employee.feed', ['employee' => $returned, 'month' => '2026-10']))
            ->assertOk()
            ->assertJsonPath('days.0.in', '09:00 AM')
            ->assertJsonPath('days.0.out', '09:40 AM')
            ->assertJsonPath('days.0.worked', '40m')
            ->assertJsonCount(4, 'days.0.punches')
            ->assertJsonPath('days.0.punches.0.direction', 'In')
            ->assertJsonPath('days.0.punches.1.direction', 'Out')
            ->assertJsonPath('days.0.punches.2.direction', 'In')
            ->assertJsonPath('days.0.punches.3.direction', 'Out');
    }

    public function test_a_date_can_be_edited_and_deleted_from_the_attendance_record(): void
    {
        $org = $this->makeCompany('EDIT');
        $hr = $this->makeUser('hr-admin', $org['company']);
        $employee = $this->makeEmployee($org, 'AT0007');
        \App\Models\AttendancePunch::query()->create([
            'employee_id' => $employee->id,
            'punched_at' => '2026-10-20 16:40:00',
            'direction' => 'in',
            'external_key' => 'edit-in',
        ]);

        $this->actingAsScoped($hr)
            ->get(route('attendance.show', ['employee' => $employee, 'date' => '2026-10-20']))
            ->assertOk()
            ->assertSee('title="Edit"', false)
            ->assertSee('title="Delete"', false);

        $this->post(route('attendance.day.update', $employee), [
            'work_date' => '2026-10-20',
            'punch_in' => '09:00',
            'punch_out' => '18:00',
        ])->assertRedirect(route('attendance.show', ['employee' => $employee, 'date' => '2026-10-20']));

        $day = \App\Models\AttendanceDay::query()->where('employee_id', $employee->id)->whereDate('work_date', '2026-10-20')->firstOrFail();
        $this->assertSame('09:00:00', (string) $day->punch_in);
        $this->assertSame('18:00:00', (string) $day->punch_out);
        $this->assertDatabaseMissing('attendance_punches', ['external_key' => 'edit-in']);

        $this->delete(route('attendance.day.destroy', $employee), ['work_date' => '2026-10-20'])
            ->assertRedirect(route('attendance.show', ['employee' => $employee, 'date' => '2026-10-20']));

        $this->assertDatabaseMissing('attendance_days', ['employee_id' => $employee->id, 'work_date' => '2026-10-20']);
        $this->assertDatabaseMissing('attendance_punches', ['employee_id' => $employee->id]);
    }

    public function test_a_lost_device_scan_is_stored_again_unless_that_scan_was_deleted(): void
    {
        $dir = storage_path('framework/testing/dahua-restore');
        File::deleteDirectory($dir);
        config([
            'dahua.push_dir' => $dir,
            'dahua.register_id' => 'GATE01',
        ]);

        $org = $this->makeCompany('RESTORE');
        $hr = $this->makeUser('hr-admin', $org['company']);
        Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Gate',
            'register_id' => 'GATE01',
            'status' => 'online',
        ]);

        $saved = $dir.DIRECTORY_SEPARATOR.'saved_files';
        File::ensureDirectoryExists($saved);
        $first = Carbon::parse('2026-10-20 09:15:00', 'Asia/Kolkata');
        File::put($saved.DIRECTORY_SEPARATOR.'21.json', json_encode([
            'DataSeq' => '21',
            'RegisterID' => 'GATE01',
            'NotifyInfo' => [
                'Events' => [[
                    'Code' => 'AccessControl',
                    'Data' => [
                        'ErrorCode' => 0,
                        'Type' => 'Entry',
                        'RealUTC' => $first->utc()->timestamp,
                        'UserID' => '900001',
                        'UserName' => 'New Person',
                        'SN' => 'GATE01',
                    ],
                ]],
            ],
        ]));

        $sync = app(\App\Services\Devices\DeviceSync::class);
        \Illuminate\Support\Facades\Cache::flush();
        $this->assertSame(1, $sync->pullPunches());
        $this->assertFileDoesNotExist($saved.DIRECTORY_SEPARATOR.'21.json');
        $this->assertFileExists($saved.DIRECTORY_SEPARATOR.'imported'.DIRECTORY_SEPARATOR.'21.json');

        \App\Models\AttendancePunch::query()->delete();
        \App\Models\AttendanceDay::query()->delete();
        File::put($saved.DIRECTORY_SEPARATOR.'imported'.DIRECTORY_SEPARATOR.'99.json', json_encode([
            'DataSeq' => '99',
            'RegisterID' => 'GATE01',
            'NotifyInfo' => [
                'Events' => [[
                    'Code' => 'AccessControl',
                    'Data' => [
                        'ErrorCode' => 0,
                        'Type' => 'Entry',
                        'RealUTC' => $first->utc()->timestamp,
                        'UserID' => 'GHOST',
                        'UserName' => 'Ghost User',
                        'SN' => 'GATE01',
                    ],
                ]],
            ],
        ]));
        \Illuminate\Support\Facades\Cache::flush();
        $sync->run(\App\Models\Device::query()->where('register_id', 'GATE01')->firstOrFail());
        $employee = \App\Models\Employee::query()->where('employee_code', '900001')->firstOrFail();
        $this->assertDatabaseHas('attendance_punches', ['employee_id' => $employee->id]);
        $this->assertDatabaseMissing('employees', ['employee_code' => 'GHOST']);

        $this->actingAsScoped($hr)
            ->delete(route('attendance.day.destroy', $employee), ['work_date' => '2026-10-20'])
            ->assertRedirect();

        \Illuminate\Support\Facades\Cache::flush();
        $sync->run(\App\Models\Device::query()->where('register_id', 'GATE01')->firstOrFail());
        $this->assertDatabaseMissing('attendance_punches', ['employee_id' => $employee->id]);

        $second = Carbon::parse('2026-10-20 11:00:00', 'Asia/Kolkata');
        File::put($saved.DIRECTORY_SEPARATOR.'22.json', json_encode([
            'DataSeq' => '22',
            'RegisterID' => 'GATE01',
            'NotifyInfo' => [
                'Events' => [[
                    'Code' => 'AccessControl',
                    'Data' => [
                        'ErrorCode' => 0,
                        'Type' => 'Entry',
                        'RealUTC' => $second->utc()->timestamp,
                        'UserID' => '900001',
                        'UserName' => 'New Person',
                        'SN' => 'GATE01',
                    ],
                ]],
            ],
        ]));
        \Illuminate\Support\Facades\Cache::flush();
        $this->assertSame(1, $sync->pullPunches());
        $this->assertDatabaseHas('attendance_days', [
            'employee_id' => $employee->id,
            'punch_in' => '11:00:00',
        ]);
    }
}
