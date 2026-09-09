<?php

namespace App\Console\Commands;

use App\Enums\AccountsReceivableAgingBracket;
use App\Enums\AccountsReceivableCollectionStatus;
use App\Enums\AccountsReceivableStatus;
use App\Enums\UserRole;
use App\Mail\AccountsReceivableReminder;
use App\Models\AccountsReceivable;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class SendAccountsReceivableReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ar:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send escalating AR reminder emails as entries cross aging brackets (AR-02).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        AccountsReceivable::query()
            ->where('status', AccountsReceivableStatus::Active->value)
            ->whereNotNull('due_at')
            ->whereNotIn('collection_status', [
                AccountsReceivableCollectionStatus::Paid->value,
                AccountsReceivableCollectionStatus::WrittenOff->value,
            ])
            ->with(['jobOrder.transactions:id,job_order_id,amount,status', 'jobOrder.queueEntry.customer:id,name'])
            ->chunkById(50, fn ($receivables) => $receivables->each(fn (AccountsReceivable $r) => $this->processOne($r)));

        return self::SUCCESS;
    }

    /**
     * Process a single AR entry: auto-close a settled balance, otherwise
     * send a reminder if it crossed into a new reminder-bearing bracket.
     */
    private function processOne(AccountsReceivable $receivable): void
    {
        $balance = $receivable->jobOrder->outstandingBalance();

        if ($balance <= 0) {
            $receivable->forceFill(['collection_status' => AccountsReceivableCollectionStatus::Paid->value])->save();

            return;
        }

        $bracket = $receivable->agingBracket();

        if (! in_array($bracket, AccountsReceivableAgingBracket::reminderBearing(), true)) {
            return;
        }

        $lastBracket = $receivable->last_reminder_bracket;
        $crossedNewBracket = $lastBracket === null || $bracket->rank() > $lastBracket->rank();

        if (! $crossedNewBracket) {
            return;
        }

        try {
            Mail::to($this->reminderRecipients())->send(new AccountsReceivableReminder($receivable, $bracket));
        } catch (\Throwable $e) {
            report($e);
        }

        $receivable->forceFill(['last_reminder_bracket' => $bracket->value, 'last_reminder_sent_at' => now()])->save();
    }

    /**
     * Accounting Staff and Owner, at every bracket (D-06) — never the
     * customer. Excludes deactivated accounts.
     *
     * @return Collection<int, string>
     */
    private function reminderRecipients(): Collection
    {
        return User::query()
            ->whereIn('role', [UserRole::AccountingStaff->value, UserRole::Owner->value])
            ->where('is_active', true)
            ->pluck('email');
    }
}
