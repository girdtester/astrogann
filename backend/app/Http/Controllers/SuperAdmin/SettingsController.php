<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\SettingsRequest;
use App\Services\ActivityLogService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * System settings (spec 3.2, 3.9): limits, reminder days, contact details and texts — never hard-coded.
 */
class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly ActivityLogService $logs,
    ) {}

    public function edit(): View
    {
        return view('superadmin.settings.edit', ['settings' => $this->settings->all()]);
    }

    public function update(SettingsRequest $request): RedirectResponse
    {
        $values = array_map(fn ($v) => $v === null ? '' : (string) $v, $request->validated());
        $values['notify_expired_users'] = $request->boolean('notify_expired_users') ? '1' : '0';
        $before = $this->settings->all();
        $this->settings->update($values);

        $changed = array_keys(array_diff_assoc($values, array_intersect_key($before, $values)));
        $this->logs->log('settings.updated', null, 'Changed: '.($changed ? implode(', ', $changed) : 'nothing'), $request->user());

        return redirect()->route('superadmin.settings.edit')->with('success', 'Settings saved.');
    }
}
