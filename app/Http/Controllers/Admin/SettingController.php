<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSettingRequest;
use App\Models\Setting;

class SettingController extends Controller
{
    public function index()
    {
        $this->authorize('isAdmin');
        $settings = Setting::first();

        return view('xpress.settings', compact('settings'));
    }

    public function store(StoreSettingRequest $request)
    {
        $this->authorize('isAdmin');

        $validated = $request->validated();

        $setting = Setting::first();

        if ($setting) {
            $setting->update($validated);

            return redirect()->back()->with('message', 'Settings updated successfully.');
        }

        Setting::create($validated);

        return redirect()->back()->with('message', 'Settings saved successfully.');
    }
}
