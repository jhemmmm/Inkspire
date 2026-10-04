<?php

namespace App\Mail;

use App\Models\JobOrder;
use App\Models\QueueEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class JobOrdersReceived extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public QueueEntry $queueEntry) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('We received your order — :number', [
                'number' => $this->queueEntry->jobOrders->first()?->number,
            ]),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.job-orders-received',
            with: [
                'jobOrders' => $this->queueEntry->jobOrders->map(fn (JobOrder $jobOrder): array => [
                    'number' => $jobOrder->number,
                    'description' => $jobOrder->description,
                    'url' => route('public.tracking.token', ['token' => $jobOrder->tracking_token]),
                ])->all(),
            ],
        );
    }
}
