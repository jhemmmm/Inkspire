<?php

namespace App\Mail;

use App\Models\OnlineOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class ConfirmOnlineOrder extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public OnlineOrder $onlineOrder) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Confirm your order'));
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.confirm-online-order',
            with: [
                'descriptions' => collect($this->onlineOrder->payload['job_orders'])->pluck('description')->all(),
                'confirmUrl' => URL::temporarySignedRoute(
                    'public.orders.confirm.show',
                    $this->onlineOrder->created_at->addHours(48),
                    ['onlineOrder' => $this->onlineOrder->id],
                ),
            ],
        );
    }
}
