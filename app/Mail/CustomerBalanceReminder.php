<?php

namespace App\Mail;

use App\Enums\AccountsReceivableAgingBracket;
use App\Models\AccountsReceivable;
use App\Support\BusinessTime;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * What Accounting sends the customer about an unpaid credit balance. Not to
 * be confused with AccountsReceivableReminder, which goes to staff and is
 * worded for them.
 */
class CustomerBalanceReminder extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public AccountsReceivable $receivable) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = match ($this->receivable->agingBracket()) {
            AccountsReceivableAgingBracket::Current => 'Reminder: balance for :number',
            AccountsReceivableAgingBracket::OneToFifteen => 'Your balance for :number is past due',
            AccountsReceivableAgingBracket::SixteenToThirty => 'Your balance for :number is :days days past due',
            AccountsReceivableAgingBracket::ThirtyOneToSixty => 'Overdue: please settle your balance for :number',
            AccountsReceivableAgingBracket::SixtyOneToNinety => 'Urgent: your balance for :number is :days days past due',
            AccountsReceivableAgingBracket::NinetyPlus => 'Final notice: your balance for :number',
        };

        return new Envelope(
            subject: __($subject, [
                'number' => $this->receivable->jobOrder->number,
                'days' => $this->receivable->daysPastDue(),
            ]),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $dueDate = $this->receivable->due_at === null
            ? null
            : BusinessTime::local($this->receivable->due_at)->format('F j, Y');

        return new Content(
            markdown: 'mail.customer-balance-reminder',
            with: [
                'customerName' => $this->receivable->jobOrder->queueEntry->customer->name,
                'body' => str_replace(
                    ['{due date}', '{n}'],
                    [$dueDate ?? '', (string) ($this->receivable->daysPastDue() ?? 0)],
                    $this->receivable->agingBracket()->emailBody(),
                ),
                'number' => $this->receivable->jobOrder->number,
                'description' => $this->receivable->jobOrder->description,
                'balance' => number_format($this->receivable->jobOrder->outstandingBalance(), 2),
                'dueDate' => $dueDate,
            ],
        );
    }
}
