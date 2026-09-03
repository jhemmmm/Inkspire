<?php

namespace App\Mail;

use App\Models\RevisionLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class DesignReviewRequested extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public RevisionLog $revisionLog)
    {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your design is ready for review — :description', ['description' => $this->revisionLog->jobOrder->description]),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.design-review-requested',
            with: [
                'jobOrderDescription' => $this->revisionLog->jobOrder->description,
                'reviewUrl' => URL::temporarySignedRoute('public.design-review.show', $this->revisionLog->submitted_at->addDays(7), ['revisionLog' => $this->revisionLog->id]),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
