<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\UpdateSystemConfigurationRequest;
use App\Models\SystemConfiguration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SystemConfigurationController extends Controller
{
    /**
     * Show the Owner/Admin system configuration screen, grouped by tab.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('owner/SystemConfiguration', [
            'configurations' => SystemConfiguration::query()
                ->orderBy('group')
                ->orderBy('label')
                ->get()
                ->groupBy('group'),
        ]);
    }

    /**
     * Update a single configuration value and invalidate its cache entry
     * immediately so the new value is live on the very next read.
     */
    public function update(UpdateSystemConfigurationRequest $request, SystemConfiguration $configuration): RedirectResponse
    {
        $configuration->update(['value' => $request->validated('value')]);

        SystemConfiguration::invalidate($configuration->key);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Configuration updated.')]);

        return back();
    }
}
