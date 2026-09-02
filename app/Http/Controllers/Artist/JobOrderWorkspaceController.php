<?php

namespace App\Http\Controllers\Artist;

use App\Enums\JobOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Artist\UpdateConsultationNotesRequest;
use App\Models\JobOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JobOrderWorkspaceController extends Controller
{
    /**
     * Render the Job Order Workspace page for a single job order assigned
     * to the acting artist.
     */
    public function show(Request $request, JobOrder $jobOrder): Response
    {
        abort_unless($jobOrder->assigned_artist_id === $request->user()->id, 403, 'This job order is not assigned to you.');

        return Inertia::render('artist/JobOrderWorkspace', [
            'jobOrder' => [
                'id' => $jobOrder->id,
                'description' => $jobOrder->description,
                'status' => $jobOrder->status->value,
                'consultation_notes' => $jobOrder->consultation_notes,
                'canEditConsultation' => $jobOrder->status === JobOrderStatus::InConsultation,
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
