<?php

namespace App\Http\Controllers\FrontlineStaff;

use App\Actions\JobOrder\ValidateJobOrderFile;
use App\Enums\JobOrderStatus;
use App\Enums\JobOrderType;
use App\Http\Controllers\Controller;
use App\Http\Requests\FrontlineStaff\ReplaceJobOrderFileRequest;
use App\Models\JobOrder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class JobOrderController extends Controller
{
    public function __construct(public ValidateJobOrderFile $validateJobOrderFile) {}

    /**
     * Replace a Type A job order's file and re-run validation in one save
     * (D-02/D-03) — Type B job orders have no file concept.
     */
    public function replaceFile(ReplaceJobOrderFileRequest $request, JobOrder $jobOrder): RedirectResponse
    {
        abort_unless($jobOrder->type === JobOrderType::TypeA, 422, 'Only a Type A job order accepts a file replacement.');

        $file = $request->file('file');
        $outcome = ($this->validateJobOrderFile)($file);

        $jobOrder->forceFill([
            'file_path' => $file->store('job-orders', 'local'),
            'status' => $outcome['passed'] ? JobOrderStatus::ReadyForProduction : JobOrderStatus::ValidationFailed,
            'validation_failure_reason' => $outcome['reason'],
        ])->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $outcome['passed']
                ? __('File replaced. Job order is ready for production.')
                : __('File replaced, but validation failed again. See the updated reason below.'),
        ]);

        return back();
    }
}
