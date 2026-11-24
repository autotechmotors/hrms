<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Operations\Concerns\UsesWorkspace;
use App\Models\CompanySetting;
use App\Services\Employees\InactivePunchBlocker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class SettingController extends Controller
{
    use UsesWorkspace;

    public function edit(Request $request, InactivePunchBlocker $blocker): View
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);

        $setting = CompanySetting::forCompany($this->companyId());
        $people = $blocker->candidates($setting);
        $term = trim((string) $request->query('overdue', ''));
        $matched = $term === ''
            ? $people
            : $people->filter(function ($person) use ($term): bool {
                $haystack = mb_strtolower($person->full_name.' '.$person->employee_code);

                return str_contains($haystack, mb_strtolower($term));
            })->values();

        $perPage = 12;
        $page = max(1, (int) $request->query('page', 1));
        $idle = new LengthAwarePaginator(
            $matched->forPage($page, $perPage)->values(),
            $matched->count(),
            $perPage,
            $page,
            ['path' => route('settings.edit'), 'pageName' => 'page'],
        );
        $idle->appends($request->except('page', 'partial'))->onEachSide(1);

        $data = [
            'setting' => $setting,
            'idle' => $idle,
            'blocker' => $blocker,
            'overdueTotal' => $people->count(),
            'overdueTerm' => $term,
        ];

        if ($request->boolean('partial')) {
            return view('admin.settings._idle', $data);
        }

        return view('admin.settings.index', $data);
    }

    public function update(Request $request, InactivePunchBlocker $blocker): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);

        $data = $request->validate([
            'block_inactive_enabled' => ['sometimes', 'boolean'],
            'block_inactive_days' => ['required', 'integer', 'min:1', 'max:365'],
            'out_inactive_minutes' => ['required', 'integer', 'min:0', 'max:240'],
        ]);

        $setting = CompanySetting::forCompany($this->companyId());
        $setting->update([
            'block_inactive_enabled' => $request->boolean('block_inactive_enabled'),
            'block_inactive_days' => (int) $data['block_inactive_days'],
            'out_inactive_minutes' => (int) $data['out_inactive_minutes'],
        ]);

        $blocked = $setting->block_inactive_enabled
            ? $blocker->apply($setting->company_id)
            : 0;

        $message = $setting->block_inactive_enabled
            ? ($blocked ? "Settings saved. {$blocked} employee(s) blocked for no in/out." : 'Settings saved. No employees needed blocking.')
            : 'Settings saved. Auto-block is off.';

        return redirect()->route('settings.edit')->with('success', $message);
    }
}
