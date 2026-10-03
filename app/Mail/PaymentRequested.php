<?php

namespace App\Mail;

use App\Models\JobOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentRequested extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public JobOrder $jobOrder) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your order :number is ready for payment', ['number' => $this->jobOrder->number]),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.payment-requested',
            with: [
                'number' => $this->jobOrder->number,
                'description' => $this->jobOrder->description,
                'amountDue' => number_format($this->jobOrder->outstandingBalance(), 2),
                'url' => route('public.tracking.token', ['token' => $this->jobOrder->tracking_token]),
            ],
        );
    }
}
