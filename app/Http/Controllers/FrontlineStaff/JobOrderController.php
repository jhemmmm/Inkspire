<?php

namespace App\Http\Controllers\FrontlineStaff;

use App\Actions\JobOrder\EnterProduction;
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
    public function __construct(
        public ValidateJobOrderFile $validateJobOrderFile,
        public EnterProduction $enterProduction,
    ) {}

    /**
     * The only statuses a file replacement may act on — everything before
     * the job order enters production. The UI only ever renders the replace
     * dialog for ValidationFailed, but the server re-checks independently
     * (RBAC-02 precedent: never trust a client-side-only decision).
     *
     * @var array<int, JobOrderStatus>
     */
    private const REPLACEABLE_STATUSES = [
        JobOrderStatus::Intake,
        JobOrderStatus::ValidationFailed,
        JobOrderStatus::ReadyForProduction,
    ];

    /**
     * Replace a Type A job order's file and re-run validation in one save
     * (D-02/D-03) — Type B job orders have no file concept.
     *
     * Guarded against replacing a file on a job order that has already
     * moved on: this path re-enters production via EnterProduction, which
     * would drag an in-production/released order back to for_production
     * with a freshly reset due_at and a falsified system-authored
     * "first entry into production" log row — or, on a failing file, park
     * it at validation_failed where no board lists it at all.
     */
    public function replaceFile(ReplaceJobOrderFileRequest $request, JobOrder $jobOrder): RedirectResponse
    {
        abort_unless($jobOrder->type === JobOrderType::TypeA, 422, 'Only a Type A job order accepts a file replacement.');
        abort_if($jobOrder->cancelled_at !== null, 422, __('This job order has been cancelled.'));
        abort_if($jobOrder->released_at !== null, 422, __('This job order has already been released.'));
        abort_unless(
            in_array($jobOrder->status, self::REPLACEABLE_STATUSES, true),
            422,
            __('This job order has already entered production and its file can no longer be replaced.'),
        );

        $file = $request->file('file');
        $outcome = ($this->validateJobOrderFile)($file);

        $jobOrder->forceFill([
            'file_path' => $file->store('job-orders', 'local'),
            'status' => $outcome['passed'] ? JobOrderStatus::ReadyForProduction : JobOrderStatus::ValidationFailed,
            'validation_failure_reason' => $outcome['reason'],
        ])->save();

        if ($outcome['passed']) {
            ($this->enterProduction)($jobOrder);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $outcome['passed']
                ? __('File replaced. Job order is ready for production.')
                : __('File replaced, but validation failed again. See the updated reason below.'),
        ]);

        return back();
    }
}
