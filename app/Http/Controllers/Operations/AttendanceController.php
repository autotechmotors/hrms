<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Operations\Concerns\UsesWorkspace;
use App\Models\AttendanceDay;
use App\Models\AttendancePunch;
use App\Models\AttendancePunchSkip;
use App\Models\AttendanceRule;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeCredential;
use App\Services\Devices\DeviceSync;
use App\Services\Operations\AttendanceCalculator;
use App\Support\Clock;
use App\Support\StoredImage;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    use UsesWorkspace;

    public function index(Request $request): View
    {
        $this->authorizeView();
        $this->pull();
        $date = Carbon::parse($request->input('date', now()->toDateString()))->startOfDay();
        $companyId = $this->companyId();
        $canEdit = $request->user()->hasPermission('employees.edit');
        $status = trim((string) $request->input('status', ''));

        $filtered = $this->activeEmployees()->with(['department', 'designation']);
        $this->applyEmployeeFilters($filtered, $request);
        $scopedIds = (clone $filtered)->pluck('id');

        $allDays = AttendanceDay::query()
            ->whereIn('employee_id', $scopedIds)
            ->whereDate('work_date', $date)
            ->get()
            ->keyBy('employee_id');

        if ($status === 'pending') {
            $filtered->whereNotIn('id', $allDays->keys()->all());
        } elseif ($status !== '') {
            $matched = $allDays->filter(fn (AttendanceDay $day) => $day->status === $status)->keys()->all();
            $filtered->whereIn('id', $matched ?: [0]);
        }

        $employees = $filtered->paginate(30)->withQueryString();
        $punches = AttendancePunch::query()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereDate('punched_at', $date)
            ->orderBy('punched_at')
            ->get()
            ->groupBy('employee_id');
        $stats = $this->dayStats($scopedIds->count(), $allDays);

        return view('operations.attendance.index', [
            'date' => $date,
            'employees' => $employees,
            'picker' => $this->activeEmployees()->get(['id', 'employee_code', 'first_name', 'middle_name', 'last_name']),
            'days' => $allDays,
            'punches' => $punches,
            'stats' => $stats,
            'departments' => Department::query()->visibleTo($request->user())->orderBy('name')->get(),
            'filters' => [
                'q' => trim((string) $request->input('q', '')),
                'department_id' => $request->input('department_id'),
                'status' => $status,
            ],
            'rule' => AttendanceRule::query()->firstOrCreate(
                ['company_id' => $companyId],
                ['grace_minutes' => 0, 'mark_absent_without_punch' => true],
            ),
            'canEdit' => $canEdit,
            'portraits' => $this->portraits($employees->getCollection()),
            'stamp' => $this->attendanceStamp($date, $scopedIds),
        ]);
    }

    public function updateRule(Request $request): RedirectResponse
    {
        $this->authorizePeople();
        $data = $request->validate([
            'grace_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'half_day_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'full_day_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'mark_absent_without_punch' => ['nullable', 'boolean'],
        ]);

        AttendanceRule::query()->updateOrCreate(
            ['company_id' => $this->companyId()],
            [
                'grace_minutes' => $data['grace_minutes'],
                'half_day_minutes' => $data['half_day_minutes'] ?: null,
                'full_day_minutes' => $data['full_day_minutes'] ?: null,
                'mark_absent_without_punch' => $request->boolean('mark_absent_without_punch'),
            ],
        );

        return back()->with('success', 'Attendance rule saved.');
    }

    public function punch(Request $request, AttendanceCalculator $calculator): RedirectResponse
    {
        $this->authorizePeople();
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'work_date' => ['required', 'date'],
            'punch_in' => ['nullable', 'date_format:H:i'],
            'punch_out' => ['nullable', 'date_format:H:i'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);
        $employee = $this->activeEmployees()->findOrFail($data['employee_id']);
        $day = AttendanceDay::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $data['work_date'])
            ->first() ?? new AttendanceDay([
                'employee_id' => $employee->id,
                'work_date' => $data['work_date'],
            ]);
        $day->fill([
            'punch_in' => $data['punch_in'] ?: null,
            'punch_out' => $data['punch_out'] ?: null,
            'remarks' => $data['remarks'] ?? null,
            'source' => 'manual',
            'status' => $day->status ?: 'absent',
        ])->save();
        $calculator->recalculate($employee, Carbon::parse($data['work_date']));

        return redirect()->route('attendance.index', array_filter([
            'date' => $data['work_date'],
            'q' => $request->input('q'),
            'department_id' => $request->input('department_id'),
            'status' => $request->input('status'),
        ]))->with('success', 'Punch saved.');
    }

    public function updateDay(Request $request, Employee $employee, AttendanceCalculator $calculator): RedirectResponse
    {
        $this->authorizePeople();
        $this->guardEmployee($employee);
        $data = $request->validate([
            'work_date' => ['required', 'date'],
            'punch_in' => ['nullable', 'date_format:H:i'],
            'punch_out' => ['nullable', 'date_format:H:i'],
        ]);
        if (($data['punch_out'] ?? null) && ! ($data['punch_in'] ?? null)) {
            return back()->with('error', 'Add the in time before the out time.');
        }
        if (($data['punch_in'] ?? null) && ($data['punch_out'] ?? null) && $data['punch_out'] <= $data['punch_in']) {
            return back()->with('error', 'Punch out must be after punch in.');
        }

        $this->replaceDay($employee, $data['work_date'], $data['punch_in'] ?? null, $data['punch_out'] ?? null, $calculator);

        return redirect()
            ->route('attendance.show', ['employee' => $employee, 'date' => $data['work_date']])
            ->with('success', ($data['punch_in'] ?? null) ? 'Attendance updated.' : 'Attendance for that date deleted.');
    }

    public function destroyDay(Request $request, Employee $employee, AttendanceCalculator $calculator): RedirectResponse
    {
        $this->authorizePeople();
        $this->guardEmployee($employee);
        $data = $request->validate([
            'work_date' => ['required', 'date'],
        ]);
        $this->replaceDay($employee, $data['work_date'], null, null, $calculator);

        return redirect()
            ->route('attendance.show', ['employee' => $employee, 'date' => $data['work_date']])
            ->with('success', 'Attendance for that date deleted.');
    }

    public function recalculate(Request $request, AttendanceCalculator $calculator): RedirectResponse
    {
        $this->authorizePeople();
        $data = $request->validate(['work_date' => ['required', 'date']]);
        $date = Carbon::parse($data['work_date'])->startOfDay();
        $this->activeEmployees()->each(fn ($employee) => $calculator->recalculate($employee, $date));

        return redirect()->route('attendance.index', ['date' => $date->toDateString()])->with('success', 'Attendance calculated from punches, shifts, leave, and holidays.');
    }

    public function show(Request $request, Employee $employee): View
    {
        $this->authorizeView();
        $this->guardEmployee($employee);
        $this->pull();
        $selected = Carbon::parse($request->input('date', now()->toDateString()))->startOfDay();
        $employee->load(['department', 'designation', 'credentials']);

        return view('operations.attendance.show', [
            'employee' => $employee,
            'month' => $selected->copy()->startOfMonth(),
            'selected' => $selected->toDateString(),
            'payload' => $this->monthPayload($employee, $selected->copy()->startOfMonth()),
            'credentials' => $employee->credentials,
            'canEdit' => $request->user()->hasPermission('employees.edit'),
        ]);
    }

    public function feed(Request $request): JsonResponse
    {
        $this->authorizeView();
        $this->pull();
        $date = Carbon::parse($request->input('date', now()->toDateString()))->startOfDay();
        $filtered = $this->activeEmployees()->with(['department', 'designation']);
        $this->applyEmployeeFilters($filtered, $request);
        $ids = (clone $filtered)->pluck('id');
        $stamp = $this->attendanceStamp($date, $ids);
        if ((string) $request->input('since') === $stamp) {
            return response()->json([
                'unchanged' => true,
                'since' => $stamp,
                'refreshed_at' => now()->timezone('Asia/Kolkata')->format('h:i:s A'),
            ]);
        }

        $employees = $filtered->get();
        $days = AttendanceDay::query()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereDate('work_date', $date)
            ->get()
            ->keyBy('employee_id');
        $punches = AttendancePunch::query()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereDate('punched_at', $date)
            ->orderBy('punched_at')
            ->get()
            ->groupBy('employee_id');

        $portraits = $this->portraits($employees);
        $rows = [];
        foreach ($employees as $employee) {
            $day = $days->get($employee->id);
            $list = $punches->get($employee->id, collect());
            $inPunch = $list->firstWhere('direction', 'in') ?? $list->first();
            $outPunch = $list->where('direction', 'out')->last();
            $status = $this->dayStatus($day);
            $rows[(string) $employee->id] = [
                'photo' => $portraits->get($employee->id),
                'in' => Clock::time($day?->punch_in),
                'out' => Clock::time($day?->punch_out),
                'in_input' => Clock::input($day?->punch_in),
                'out_input' => Clock::input($day?->punch_out),
                'worked' => $this->workedLabel($day?->minutes_worked),
                'status' => $status['label'],
                'status_class' => $status['class'],
                'in_photo' => $this->photoUrl($inPunch?->image, 'attendance.punch.photo', $inPunch),
                'out_photo' => $this->photoUrl($outPunch?->image, 'attendance.punch.photo', $outPunch),
            ];
        }

        return response()->json([
            'refreshed_at' => now()->timezone('Asia/Kolkata')->format('h:i:s A'),
            'since' => $stamp,
            'rows' => $rows,
        ]);
    }

    public function employeeFeed(Request $request, Employee $employee): JsonResponse
    {
        $this->authorizeView();
        $this->guardEmployee($employee);
        $this->pull();
        $month = $request->filled('month')
            ? Carbon::parse($request->string('month')->toString().'-01')->startOfMonth()
            : Carbon::parse($request->input('date', now()->toDateString()))->startOfMonth();
        $stamp = $this->monthStamp($employee, $month);
        if ((string) $request->input('since') === $stamp) {
            return response()->json([
                'unchanged' => true,
                'since' => $stamp,
                'refreshed_at' => now()->timezone('Asia/Kolkata')->format('h:i:s A'),
            ]);
        }

        $employee->load('credentials');

        return response()->json($this->monthPayload($employee, $month));
    }

    public function punchPhoto(AttendancePunch $punch): Response
    {
        $this->authorizeView();
        $punch->loadMissing('employee');
        $this->guardEmployee($punch->employee);

        return $this->imageResponse($punch->image);
    }

    public function credentialPhoto(EmployeeCredential $credential): Response
    {
        $this->authorizeView();
        $credential->loadMissing('employee');
        $this->guardEmployee($credential->employee);

        return $this->imageResponse($credential->content);
    }

    protected function applyEmployeeFilters(Builder $query, Request $request): void
    {
        $q = trim((string) $request->input('q', ''));
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function (Builder $inner) use ($like) {
                $inner->where('employee_code', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('middle_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }
    }

    /**
     * @param  Collection<int, AttendanceDay>  $days
     * @return array<string, int>
     */
    protected function dayStats(int $totalEmployees, Collection $days): array
    {
        $counts = [
            'present' => 0,
            'late' => 0,
            'half_day' => 0,
            'absent' => 0,
            'on_leave' => 0,
            'holiday' => 0,
            'weekly_off' => 0,
            'unmarked' => 0,
            'pending' => max(0, $totalEmployees - $days->count()),
        ];

        foreach ($days as $day) {
            if (array_key_exists($day->status, $counts)) {
                $counts[$day->status]++;
            }
        }

        $counts['working'] = $counts['present'] + $counts['late'] + $counts['half_day'];

        return $counts;
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @return Collection<int, string>
     */
    protected function portraits(Collection $employees): Collection
    {
        if ($employees->isEmpty()) {
            return collect();
        }

        $faces = EmployeeCredential::query()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->where('kind', 'face')
            ->orderBy('slot')
            ->get()
            ->groupBy('employee_id');

        return $employees->mapWithKeys(function (Employee $employee) use ($faces) {
            $face = $faces->get($employee->id, collect())->first(fn (EmployeeCredential $item) => StoredImage::mimeOf($item->content));
            if ($face) {
                return [$employee->id => route('attendance.credential.photo', $face)];
            }
            if (filled($employee->photograph_path)) {
                return [$employee->id => route('employees.photo', $employee)];
            }

            return [$employee->id => null];
        });
    }

    protected function pull(): void
    {
        try {
            app(DeviceSync::class)->pullPunches();
        } catch (\Throwable) {
            // The page still shows punches already stored.
        }
    }

    protected function guardEmployee(?Employee $employee): void
    {
        abort_unless($employee && (int) $employee->company_id === $this->companyId(), 404);
    }

    /**
     * @return array<string, mixed>
     */
    protected function monthPayload(Employee $employee, Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $face = $employee->credentials->first(fn (EmployeeCredential $item) => $item->kind === 'face' && StoredImage::mimeOf($item->content));
        $faceUrl = $face ? route('attendance.credential.photo', $face) : null;
        $punches = AttendancePunch::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('punched_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->orderBy('punched_at')
            ->get()
            ->groupBy(fn (AttendancePunch $punch) => $punch->punched_at->timezone('Asia/Kolkata')->toDateString());
        $storedDays = AttendanceDay::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (AttendanceDay $day) => $day->work_date->toDateString());

        $days = $punches->keys()->merge($storedDays->keys())->unique()->sortDesc()->values()->map(function (string $date) use ($punches, $storedDays, $faceUrl) {
            $list = app(DeviceSync::class)->collapse($punches->get($date, collect())->values());
            $day = $storedDays->get($date);
            $inPunch = $list->firstWhere('direction', 'in') ?? $list->first();
            $outPunch = $list->where('direction', 'out')->last();
            $status = $this->dayStatus($day);
            $entry = $this->shotFor($inPunch, $faceUrl);

            return [
                'date' => $date,
                'label' => Carbon::parse($date)->format('D, d M'),
                'in' => $day?->punch_in ? Clock::time($day->punch_in) : ($inPunch ? Clock::time($inPunch->punched_at) : null),
                'out' => $day?->punch_out ? Clock::time($day->punch_out) : ($outPunch ? Clock::time($outPunch->punched_at) : null),
                'worked' => $this->workedLabel($day?->minutes_worked),
                'status' => $status['label'],
                'status_class' => $status['class'],
                'in_photo' => $entry['photo'],
                'in_note' => $entry['note'],
                'in_input' => Clock::input($day?->punch_in ?: $inPunch?->punched_at),
                'out_input' => Clock::input($day?->punch_out ?: ($outPunch?->punched_at)),
                'punches' => $list->map(fn (AttendancePunch $punch) => [
                    'time' => Clock::time($punch->punched_at),
                    'direction' => $punch->direction === 'out' ? 'Out' : 'In',
                ])->values()->all(),
            ];
        })->values()->all();

        return [
            'refreshed_at' => now()->timezone('Asia/Kolkata')->format('h:i:s A'),
            'since' => $this->monthStamp($employee, $start),
            'face' => $faceUrl,
            'days' => $days,
        ];
    }

    /**
     * @return array{photo: ?string, note: ?string}
     */
    protected function replaceDay(Employee $employee, string $date, ?string $in, ?string $out, AttendanceCalculator $calculator): void
    {
        $dayStart = Carbon::parse($date, 'Asia/Kolkata')->startOfDay();
        $existing = AttendancePunch::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('punched_at', [$dayStart->copy(), $dayStart->copy()->endOfDay()])
            ->get();
        foreach ($existing as $punch) {
            if ($punch->external_key) {
                AttendancePunchSkip::query()->firstOrCreate(['external_key' => $punch->external_key]);
            }
        }
        if ($existing->isNotEmpty()) {
            AttendancePunch::query()
                ->whereIn('id', $existing->pluck('id'))
                ->delete();
        }

        $inAt = $in ? Carbon::parse($date.' '.$in, 'Asia/Kolkata') : null;
        $outAt = $out ? Carbon::parse($date.' '.$out, 'Asia/Kolkata') : null;
        foreach (['in' => $inAt, 'out' => $outAt] as $direction => $at) {
            if (! $at) {
                continue;
            }
            AttendancePunch::query()->create([
                'employee_id' => $employee->id,
                'punched_at' => $at,
                'direction' => $direction,
                'external_key' => 'manual|'.$employee->id.'|'.$date.'|'.$direction,
            ]);
        }

        $stored = AttendanceDay::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $date)
            ->first();
        if (! $inAt && ! $outAt) {
            $stored?->delete();

            return;
        }

        $stored = $stored ?? new AttendanceDay([
            'employee_id' => $employee->id,
            'work_date' => $date,
        ]);
        $stored->fill([
            'punch_in' => $inAt?->format('H:i:s'),
            'punch_out' => $outAt?->format('H:i:s'),
            'source' => 'manual',
            'status' => $stored->status ?: 'absent',
        ])->save();
        $calculator->recalculate($employee, $dayStart);
    }

    protected function shotFor(?AttendancePunch $punch, ?string $faceUrl): array
    {
        if (! $punch) {
            return ['photo' => null, 'note' => null];
        }

        $photo = $this->photoUrl($punch->image, 'attendance.punch.photo', $punch);
        if ($photo) {
            return ['photo' => $photo, 'note' => 'Door photo'];
        }

        if ($faceUrl) {
            return ['photo' => $faceUrl, 'note' => 'Enrolled face'];
        }

        return ['photo' => null, 'note' => null];
    }

    protected function photoUrl(?string $stored, string $name, mixed $model): ?string
    {
        if (! $model || StoredImage::mimeOf($stored) === null) {
            return null;
        }

        return route($name, $model);
    }

    protected function imageResponse(?string $stored): Response
    {
        $mime = StoredImage::mimeOf($stored);
        $bytes = StoredImage::bytes($stored);
        abort_unless($mime && $bytes, 404);

        return response($bytes, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    /**
     * @return array{label: string, class: string}
     */
    protected function dayStatus(?AttendanceDay $day): array
    {
        $status = $day?->status;
        if ($day?->punch_in && ($status === null || $status === 'absent' || $status === 'pending')) {
            $status = 'present';
        }

        return $this->statusMeta($status);
    }

    /**
     * @param  Collection<int, int>  $employeeIds
     */
    protected function attendanceStamp(Carbon $date, Collection $employeeIds): string
    {
        if ($employeeIds->isEmpty()) {
            return md5($date->toDateString());
        }

        $day = AttendanceDay::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('work_date', $date)
            ->max('updated_at');
        $punch = AttendancePunch::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('punched_at', $date)
            ->max('updated_at');

        return md5($date->toDateString().'|'.$day.'|'.$punch.'|'.$employeeIds->count());
    }

    protected function monthStamp(Employee $employee, Carbon $month): string
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $day = AttendanceDay::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->max('updated_at');
        $punch = AttendancePunch::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('punched_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->max('updated_at');

        return md5($employee->id.'|'.$start->toDateString().'|'.$day.'|'.$punch);
    }

    protected function statusMeta(?string $status): array
    {
        $labels = [
            'present' => 'Present',
            'late' => 'Late',
            'half_day' => 'Half day',
            'absent' => 'Absent',
            'on_leave' => 'On leave',
            'holiday' => 'Holiday',
            'weekly_off' => 'Weekly off',
            'unmarked' => 'Unmarked',
        ];
        $classes = [
            'present' => 'ok',
            'late' => 'wn',
            'half_day' => 'wn',
            'absent' => 'er',
            'on_leave' => 'ac',
            'holiday' => 'ac',
            'weekly_off' => '',
            'unmarked' => 'wn',
        ];
        $key = $status ?: 'pending';

        return [
            'label' => $labels[$key] ?? 'Not calculated',
            'class' => $classes[$key] ?? '',
        ];
    }

    protected function workedLabel(mixed $minutes): string
    {
        if ($minutes === null || $minutes === '') {
            return '—';
        }

        $minutes = (int) $minutes;
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return $hours > 0 ? sprintf('%dh %02dm', $hours, $rest) : sprintf('%dm', $rest);
    }
}
