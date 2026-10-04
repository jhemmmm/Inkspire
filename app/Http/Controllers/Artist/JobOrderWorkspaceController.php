<?php

namespace App\Http\Controllers\Artist;

use App\Enums\JobOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Artist\UpdateConsultationNotesRequest;
use App\Models\JobOrder;
use App\Models\RevisionLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class JobOrderWorkspaceController extends Controller
{
    /** Open a historical file with a fresh signed storage URL. */
    public function revisionFile(Request $request, JobOrder $jobOrder, RevisionLog $revisionLog): RedirectResponse
    {
        abort_unless($jobOrder->assigned_artist_id === $request->user()->id, 403, 'This job order is not assigned to you.');
        abort_unless($revisionLog->job_order_id === $jobOrder->id, 404);
        abort_if($revisionLog->file_path === null || ! Storage::disk('local')->exists($revisionLog->file_path), 404);

        return redirect()->away(Storage::disk('local')->temporaryUrl($revisionLog->file_path, now()->addMinutes(10)));
    }

    /**
     * Open the stored design at full size. Signs a fresh URL on every click,
     * so the link still works on a workspace left open longer than the
     * 10 minutes the inline preview's own URL lasts.
     */
    public function design(Request $request, JobOrder $jobOrder): RedirectResponse
    {
        abort_unless($jobOrder->assigned_artist_id === $request->user()->id, 403, 'This job order is not assigned to you.');
        abort_if($jobOrder->designFile?->file_path === null, 404);

        return redirect()->away(Storage::disk('local')->temporaryUrl($jobOrder->designFile->file_path, now()->addMinutes(10)));
    }

    /**
     * Render the Job Order Workspace page for a single job order assigned
     * to the acting artist.
     */
    public function show(Request $request, JobOrder $jobOrder): Response
    {
        abort_unless($jobOrder->assigned_artist_id === $request->user()->id, 403, 'This job order is not assigned to you.');

        $jobOrder->loadMissing(['designFile', 'queueEntry.customer:id,name,organization', 'revisionLogs' => fn ($query) => $query->orderBy('submitted_at')->orderBy('id')]);

        return Inertia::render('artist/JobOrderWorkspace', [
            'jobOrder' => [
                'id' => $jobOrder->id,
                'number' => $jobOrder->number,
                'description' => $jobOrder->description,
                'status' => $jobOrder->status->value,
                'type' => $jobOrder->type->value,
                'is_rush' => $jobOrder->is_rush,
                'deadline' => $jobOrder->deadline?->toDateString(),
                // Staff-side screen behind role:artist -- the customer's name
                // is deliberately shown here, unlike the public tracking page.
                'customer_name' => $jobOrder->queueEntry?->customer?->name,
                'customer_organization' => $jobOrder->queueEntry?->customer?->organization,
                'consultation_notes' => $jobOrder->consultation_notes,
                // The customer's own words, captured at the counter.
                'client_notes' => $jobOrder->client_notes,
                'print_size' => $jobOrder->print_size,
                'width_ft' => $jobOrder->width_ft,
                'height_ft' => $jobOrder->height_ft,
                'quantity' => $jobOrder->quantity,
                // Populated when a Type A file was too low-resolution for the
                // size ordered -- this is the artist's brief for what to fix.
                'validation_failure_reason' => $jobOrder->validation_failure_reason,
                'canEditConsultation' => $jobOrder->status === JobOrderStatus::InConsultation,
            ],
            'design' => [
                // What the customer actually handed over at the counter. The
                // artist was previously shown the validation VERDICT without
                // the file it was passed on, which is the one thing they need
                // to act on it.
                'customerFileUrl' => $jobOrder->file_path
                    ? Storage::disk('local')->temporaryUrl($jobOrder->file_path, now()->addMinutes(30))
                    : null,
                'initialImageUrl' => $jobOrder->designFile?->file_path
                    ? Storage::disk('local')->temporaryUrl($jobOrder->designFile->file_path, now()->addMinutes(10))
                    : null,
                'canEdit' => $jobOrder->status !== JobOrderStatus::Assigned && optional($jobOrder->designFile)->locked_at === null,
            ],
            'review' => [
                'canRecordVerdict' => $jobOrder->status === JobOrderStatus::PendingReview,
                'revisionLogs' => $jobOrder->revisionLogs->map(fn (RevisionLog $log, int $index) => [
                    'id' => $log->id,
                    'version' => $index + 1,
                    'submitted_at' => $log->submitted_at,
                    'outcome' => $log->outcome,
                    'reviewed_at' => $log->reviewed_at,
                    'message' => $log->message,
                    'has_file' => $log->file_path !== null && Storage::disk('local')->exists($log->file_path),
                    'file_url' => $log->file_path !== null ? route('artist.job-orders.revisions.file', [$jobOrder, $log]) : null,
                ])->reverse()->values(),
            ],
        ]);
    }

    /**
     * Save consultation notes on a job order while it is in_consultation.
     */
    public function updateConsultation(UpdateConsultationNotesRequest $request, JobOrder $jobOrder): RedirectResponse
    {
        abort_unless($jobOrder->assigned_artist_id === $request->user()->id, 403, 'This job order is not assigned to you.');
        abort_unless($jobOrder->status === JobOrderStatus::InConsultation, 422, 'Consultation notes can only be edited while the job order is in consultation.');

        $jobOrder->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Consultation notes saved.')]);

        return back();
    }
}
