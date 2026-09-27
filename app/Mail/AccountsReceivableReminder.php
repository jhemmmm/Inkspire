<?php

namespace App\Mail;

use App\Enums\AccountsReceivableAgingBracket;
use App\Enums\AccountsReceivableCollectionStatus;
use App\Models\AccountsReceivable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountsReceivableReminder extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public AccountsReceivable $receivable, public AccountsReceivableAgingBracket $bracket)
    {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectFor(),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.accounts-receivable-reminder',
            with: [
                'lead' => $this->leadFor(),
                'closing' => $this->closingFor(),
                'customerName' => $this->receivable->jobOrder->queueEntry?->customer?->name,
                'jobOrderNumber' => $this->receivable->jobOrder->number,
                'outstandingBalance' => $this->outstandingBalance(),
                'dueDateFormatted' => $this->receivable->due_at?->format('F j, Y'),
                'daysPastDue' => $this->receivable->daysPastDue(),
                'collectionStatusLabel' => $this->collectionStatusLabel(),
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

    /**
     * The escalating subject line for this reminder's bracket (UI-SPEC's
     * Copywriting Contract, "Reminder emails" section).
     */
    private function subjectFor(): string
    {
        $number = $this->receivable->jobOrder->number;
        $daysPastDue = $this->receivable->daysPastDue();

        return match ($this->bracket) {
            AccountsReceivableAgingBracket::OneToFifteen => "AR notice — {$number} is {$daysPastDue} days past due",
            AccountsReceivableAgingBracket::SixteenToThirty => "Urgent — {$number} is {$daysPastDue} days past due",
            AccountsReceivableAgingBracket::ThirtyOneToSixty => "Escalation — {$number} is {$daysPastDue} days past due",
            AccountsReceivableAgingBracket::NinetyPlus => "Final notice — {$number} is {$daysPastDue} days past due, write-off decision needed",
            default => "AR notice — {$number} is {$daysPastDue} days past due",
        };
    }

    /**
     * The bracket-driven lead paragraph (UI-SPEC's Copywriting Contract).
     */
    private function leadFor(): string
    {
        return match ($this->bracket) {
            AccountsReceivableAgingBracket::OneToFifteen => 'A credit balance has passed its due date.',
            AccountsReceivableAgingBracket::SixteenToThirty => 'A credit balance is now more than two weeks overdue and needs follow-up.',
            AccountsReceivableAgingBracket::ThirtyOneToSixty => 'A credit balance is now more than a month overdue. Earlier reminders have not been settled.',
            AccountsReceivableAgingBracket::NinetyPlus => 'A credit balance is more than 90 days overdue. This account should be collected or written off.',
            default => 'A credit balance has passed its due date.',
        };
    }

    /**
     * The bracket-driven closing paragraph, naming where each role acts
     * (binding rule: no deep link into either portal).
     */
    private function closingFor(): string
    {
        return match ($this->bracket) {
            AccountsReceivableAgingBracket::NinetyPlus => 'Accounting Staff: print a final collection letter, or submit a write-off request from the entry. Admin: approved write-off requests are actioned under Write-Off Requests.',
            default => 'Accounting Staff: open Accounts Receivable in '.config('app.name').' to update the collection status or print a collection letter.',
        };
    }

    /**
     * The derived outstanding balance — delegates to
     * JobOrder::outstandingBalance() (D-16), the single source of truth.
     */
    private function outstandingBalance(): float
    {
        return $this->receivable->jobOrder->outstandingBalance();
    }

    /**
     * A human label for the entry's current collection status.
     */
    private function collectionStatusLabel(): string
    {
        return match ($this->receivable->collectionStatus()) {
            AccountsReceivableCollectionStatus::Pending => 'Pending',
            AccountsReceivableCollectionStatus::FollowUp => 'Follow-up',
            AccountsReceivableCollectionStatus::WarningSent => 'Warning Sent',
            AccountsReceivableCollectionStatus::Collections => 'Collections',
            AccountsReceivableCollectionStatus::Paid => 'Paid',
            AccountsReceivableCollectionStatus::WrittenOff => 'Written Off',
            AccountsReceivableCollectionStatus::Cancelled => 'Cancelled',
        };
    }
}
