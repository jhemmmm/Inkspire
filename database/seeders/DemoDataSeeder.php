<?php

namespace Database\Seeders;

use App\Enums\AccountsReceivableAgingBracket;
use App\Enums\AccountsReceivableStatus;
use App\Enums\ArtistStatus;
use App\Enums\JobOrderStatus;
use App\Enums\JobOrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\AccountsReceivable;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\JobOrder;
use App\Models\ProductionLog;
use App\Models\QueueEntry;
use App\Models\SystemConfiguration;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;

/**
 * Demo/UAT seeder for Phase 8 (Reports) manual verification -- NOT part of
 * the automated test suite and NOT called from DatabaseSeeder::run(). Run
 * explicitly:
 *
 *     php artisan db:seed --class=DemoDataSeeder
 *
 * Creates one login for each of the seven roles plus a month's worth of
 * report-bearing data (job orders, transactions, expenses, an approved AR
 * write-off) so every report in App\Services\Reports\ReportRegistry renders
 * non-empty rows for a "this month" date range, for every role entitled to
 * see it -- and so the Artist and Frontline Staff portals, which no report
 * covers, also open onto real work rather than empty states.
 *
 * Role accounts are updateOrCreate'd on email, so re-running is safe for
 * logins. Job order/transaction/expense/AR rows are appended fresh on every
 * run (no dedup) -- fine for a scratch demo DB, never point this at a real
 * one. All dates are anchored to the current calendar month -- if a UAT
 * cycle crosses a month boundary, re-run this seeder.
 */
class DemoDataSeeder extends Seeder
{
    private const string DEMO_PASSWORD = 'DemoPass123!';

    /**
     * @var array<string, User>
     */
    private array $accounts = [];

    public function run(): void
    {
        $this->seedRoleAccounts();
        $this->seedSalesData();
        $this->seedCancellations();
        $this->seedProductionPipeline();
        $this->seedArtistQueue();
        $this->seedWaitingQueue();
        $this->seedExpenses();
        $this->seedWriteOff();
        $this->seedAgingReceivables();

        $this->printCredentials();
    }

    /**
     * One login per role needed for Phase 8 verification.
     */
    private function seedRoleAccounts(): void
    {
        $definitions = [
            'admin' => ['name' => 'Demo Admin', 'email' => 'admin@inkspire.test', 'role' => UserRole::Admin],
            'frontline' => ['name' => 'Demo Frontline Staff', 'email' => 'frontline@inkspire.test', 'role' => UserRole::FrontlineStaff],
            'artist' => ['name' => 'Demo Artist', 'email' => 'artist@inkspire.test', 'role' => UserRole::Artist],
            'cashier' => ['name' => 'Demo Cashier', 'email' => 'cashier@inkspire.test', 'role' => UserRole::Cashier],
            'production' => ['name' => 'Demo Production Staff', 'email' => 'production@inkspire.test', 'role' => UserRole::ProductionStaff],
            'accounting' => ['name' => 'Demo Accounting Staff', 'email' => 'accounting@inkspire.test', 'role' => UserRole::AccountingStaff],
        ];

        foreach ($definitions as $key => $definition) {
            $user = User::query()->updateOrCreate(
                ['email' => $definition['email']],
                [
                    'name' => $definition['name'],
                    'password' => self::DEMO_PASSWORD,
                    'role' => $definition['role']->value,
                    'email_verified_at' => now(),
                ],
            );

            /**
             * `is_active` sits outside User's #[Fillable] list by design.
             * `artist_status` is only written for the Artist -- it is a
             * NOT NULL column carrying a DB-level default, so echoing the
             * unrefreshed (null) attribute back for other roles would
             * violate the constraint.
             */
            $attributes = ['is_active' => true];

            if ($definition['role'] === UserRole::Artist) {
                $attributes['artist_status'] = ArtistStatus::Available;
                // The label a customer is sent to. Assigned here rather than
                // left null so a freshly seeded install matches what
                // UserManagementController::store() produces for real hires.
                $attributes['artist_label'] = $user->artist_label ?? User::nextArtistLabel();
            }

            $user->forceFill($attributes)->save();

            $this->accounts[$key] = $user;
        }
    }

    /**
     * Ten paid job orders spanning cash/bank transfer/GCash/Maya and
     * down+balance/full payment splits, all confirmed this month -- feeds
     * the Sales report and the Financial Summary's job_sales figure.
     */
    private function seedSalesData(): void
    {
        $cashier = $this->accounts['cashier'];

        $orders = [
            ['total' => 3000, 'status' => PaymentStatus::Paid, 'day' => 9, 'payments' => [
                [TransactionType::FullPayment, 3000, PaymentMethod::Cash, 9],
            ]],
            ['total' => 4500, 'status' => PaymentStatus::Paid, 'day' => 8, 'payments' => [
                [TransactionType::FullPayment, 4500, PaymentMethod::BankTransfer, 8],
            ]],
            ['total' => 2500, 'status' => PaymentStatus::PartiallyPaid, 'day' => 7, 'payments' => [
                [TransactionType::DownPayment, 1000, PaymentMethod::Gcash, 7],
            ]],
            ['total' => 6000, 'status' => PaymentStatus::Paid, 'day' => 6, 'payments' => [
                [TransactionType::FullPayment, 6000, PaymentMethod::Maya, 6],
            ]],
            ['total' => 3500, 'status' => PaymentStatus::Paid, 'day' => 5, 'payments' => [
                [TransactionType::DownPayment, 1500, PaymentMethod::Cash, 3],
                [TransactionType::BalancePayment, 2000, PaymentMethod::Gcash, 5],
            ]],
            ['total' => 2000, 'status' => PaymentStatus::Paid, 'day' => 4, 'payments' => [
                [TransactionType::FullPayment, 2000, PaymentMethod::Cash, 4],
            ]],
            ['total' => 5000, 'status' => PaymentStatus::Paid, 'day' => 3, 'payments' => [
                [TransactionType::FullPayment, 5000, PaymentMethod::BankTransfer, 3],
            ]],
            ['total' => 1800, 'status' => PaymentStatus::PartiallyPaid, 'day' => 2, 'payments' => [
                [TransactionType::DownPayment, 800, PaymentMethod::Maya, 2],
            ]],
            ['total' => 4000, 'status' => PaymentStatus::Paid, 'day' => 2, 'payments' => [
                [TransactionType::FullPayment, 4000, PaymentMethod::Gcash, 2],
            ]],
            ['total' => 2700, 'status' => PaymentStatus::Paid, 'day' => 1, 'payments' => [
                [TransactionType::FullPayment, 2700, PaymentMethod::Cash, 1],
            ]],
        ];

        foreach ($orders as $index => $order) {
            $day = $this->dayOfMonth($order['day']);

            $jobOrder = $this->newJobOrder($day, [
                'type' => $index % 2 === 0 ? JobOrderType::TypeA->value : JobOrderType::TypeB->value,
                'status' => JobOrderStatus::ReadyForPickup->value,
                'total_amount' => $order['total'],
                'payment_status' => $order['status']->value,
                'due_at' => $day->copy()->addDays(2),
            ]);

            foreach ($order['payments'] as [$type, $amount, $method, $confirmDay]) {
                Transaction::factory()->create([
                    'job_order_id' => $jobOrder->id,
                    'type' => $type->value,
                    'payment_method' => $method->value,
                    'amount' => $amount,
                    'confirmed_at' => $this->dayOfMonth($confirmDay),
                    'recorded_by' => $cashier->id,
                ]);
            }
        }
    }

    /**
     * Three cancelled job orders this month -- two with a cancellation fee
     * transaction, one without (fee already covered by a prior payment,
     * mirroring CashierReportsTest's zero-fee scenario).
     */
    private function seedCancellations(): void
    {
        $cashier = $this->accounts['cashier'];

        $day1 = $this->dayOfMonth(6);
        $order1 = $this->newJobOrder($day1, [
            'total_amount' => 1000,
            'cancelled_at' => $day1,
        ]);
        Transaction::factory()->create([
            'job_order_id' => $order1->id,
            'type' => TransactionType::CancellationFee->value,
            'payment_method' => PaymentMethod::Cash->value,
            'amount' => 500,
            'confirmed_at' => $day1,
            'recorded_by' => $cashier->id,
        ]);

        $day2 = $this->dayOfMonth(4);
        $order2 = $this->newJobOrder($day2, [
            'total_amount' => 2000,
            'cancelled_at' => $day2,
        ]);
        Transaction::factory()->create([
            'job_order_id' => $order2->id,
            'type' => TransactionType::CancellationFee->value,
            'payment_method' => PaymentMethod::BankTransfer->value,
            'amount' => 500,
            'confirmed_at' => $day2,
            'recorded_by' => $cashier->id,
        ]);

        $day3 = $this->dayOfMonth(3);
        $this->newJobOrder($day3, [
            'total_amount' => 1500,
            'cancelled_at' => $day3,
        ]);
    }

    /**
     * Eight job orders that entered production this month (a ProductionLog
     * row with to_status=for_production), spread across later stages with
     * a mix of overdue and future due dates -- feeds the Production Status
     * report's stage/urgency columns.
     */
    private function seedProductionPipeline(): void
    {
        $production = $this->accounts['production'];

        $stages = [
            ['status' => JobOrderStatus::ForProduction, 'dueOffset' => 2, 'day' => 9],
            ['status' => JobOrderStatus::ForProduction, 'dueOffset' => -1, 'day' => 8],
            ['status' => JobOrderStatus::Printing, 'dueOffset' => 0, 'day' => 7],
            ['status' => JobOrderStatus::Printing, 'dueOffset' => 5, 'day' => 6],
            ['status' => JobOrderStatus::Printing, 'dueOffset' => -3, 'day' => 5],
            ['status' => JobOrderStatus::Printing, 'dueOffset' => 3, 'day' => 4],
            ['status' => JobOrderStatus::ReadyForPickup, 'dueOffset' => -5, 'day' => 3],
            ['status' => JobOrderStatus::ReadyForPickup, 'dueOffset' => 7, 'day' => 2],
        ];

        foreach ($stages as $index => $stage) {
            $day = $this->dayOfMonth($stage['day']);

            $jobOrder = $this->newJobOrder($day, [
                'status' => $stage['status']->value,
                'total_amount' => 1500 + ($index * 350),
                'due_at' => $this->dueOffset($stage['dueOffset']),
            ]);

            ProductionLog::factory()->create([
                'job_order_id' => $jobOrder->id,
                'from_status' => JobOrderStatus::DesignApproved->value,
                'to_status' => JobOrderStatus::ForProduction->value,
                'recorded_by' => $production->id,
                'created_at' => $day,
            ]);
        }
    }

    /**
     * Five job orders assigned to the demo Artist, one per stage their
     * dashboard actually lists (JobOrderQueueController::index excludes
     * intake, validation_failed, ready_for_production and everything from
     * for_production onward). The oldest `assigned` row is the one "Call
     * Next Customer" will claim.
     */
    private function seedArtistQueue(): void
    {
        $artist = $this->accounts['artist'];

        $stages = [
            ['status' => JobOrderStatus::Assigned, 'day' => 2, 'description' => 'Tarpaulin, 4x6ft - birthday banner'],
            ['status' => JobOrderStatus::Assigned, 'day' => 4, 'description' => 'Sticker sheet, A4 - logo decals'],
            ['status' => JobOrderStatus::InConsultation, 'day' => 6, 'description' => 'Business cards, 500pcs - matte finish'],
            ['status' => JobOrderStatus::InDesign, 'day' => 7, 'description' => 'Roll-up banner, 2x5ft - trade show'],
            ['status' => JobOrderStatus::PendingReview, 'day' => 8, 'description' => 'Menu board, 3x4ft - cafe signage'],
        ];

        foreach ($stages as $stage) {
            $day = $this->dayOfMonth($stage['day']);

            $customer = Customer::factory()->create();

            $queueEntry = QueueEntry::factory()->serving()->create([
                'customer_id' => $customer->id,
                'queue_date' => $day->toDateString(),
            ]);

            JobOrder::factory()->create([
                'queue_entry_id' => $queueEntry->id,
                'type' => JobOrderType::TypeB->value,
                'status' => $stage['status']->value,
                'description' => $stage['description'],
                'assigned_artist_id' => $artist->id,
                'accepted_at' => $day,
                'created_at' => $day,
                'due_at' => $this->dueOffset(3),
            ]);
        }
    }

    /**
     * Three customers still waiting in today's queue so the Frontline
     * Staff queue monitor and Queue List aren't empty -- every other
     * section marks its queue entries `done`.
     */
    private function seedWaitingQueue(): void
    {
        foreach (['Walk-in - tarpaulin inquiry', 'Walk-in - sticker reprint', 'Walk-in - ID lace order'] as $description) {
            $customer = Customer::factory()->create();

            $queueEntry = QueueEntry::factory()->create([
                'customer_id' => $customer->id,
                'queue_date' => now()->toDateString(),
            ]);

            JobOrder::factory()->create([
                'queue_entry_id' => $queueEntry->id,
                'type' => JobOrderType::TypeB->value,
                'status' => JobOrderStatus::Intake->value,
                'description' => $description,
            ]);
        }
    }

    /**
     * Seven active expenses across every configured category, plus one
     * voided expense (excluded from every sum, D-11) -- feeds the Expenses
     * report and the Financial Summary's expenses_total.
     */
    private function seedExpenses(): void
    {
        $accounting = $this->accounts['accounting'];

        $entries = [
            ['category' => 'Utilities', 'amount' => 1500, 'day' => 9, 'description' => 'Electricity bill - this month'],
            ['category' => 'Supplies', 'amount' => 2200, 'day' => 8, 'description' => 'Ink and vinyl roll restock'],
            ['category' => 'Rent', 'amount' => 8000, 'day' => 5, 'description' => 'Shop space rent - this month'],
            ['category' => 'Utilities', 'amount' => 900, 'day' => 7, 'description' => 'Water bill'],
            ['category' => 'Supplies', 'amount' => 1200, 'day' => 6, 'description' => 'Business card stock paper'],
            ['category' => 'Supplies', 'amount' => 600, 'day' => 4, 'description' => 'Lamination film'],
            ['category' => 'Utilities', 'amount' => 750, 'day' => 3, 'description' => 'Internet subscription'],
        ];

        foreach ($entries as $entry) {
            Expense::factory()->create([
                'category' => $entry['category'],
                'amount' => $entry['amount'],
                'expense_date' => $this->dayOfMonth($entry['day']),
                'description' => $entry['description'],
                'recorded_by' => $accounting->id,
            ]);
        }

        // Voided -- shows in the report row list with a "Voided" status
        // badge, but must never contribute to any active sum (D-11).
        Expense::factory()->voided()->create([
            'category' => 'Supplies',
            'amount' => 999,
            'expense_date' => $this->dayOfMonth(9),
            'description' => 'Duplicate ink cartridge order (voided)',
            'recorded_by' => $accounting->id,
        ]);
    }

    /**
     * One On-Credit job order approved and then written off this month --
     * exercises the Financial Summary's write-off disclosure line (D-09/
     * D-10), which Plan 08-03 added `written_off_at` specifically for.
     */
    private function seedWriteOff(): void
    {
        $admin = $this->accounts['admin'];
        $cashier = $this->accounts['cashier'];
        $accounting = $this->accounts['accounting'];

        $day = $this->dayOfMonth(9);

        $jobOrder = $this->newJobOrder($day->copy()->subDays(20), [
            'status' => JobOrderStatus::ReadyForPickup->value,
            'total_amount' => 800,
            'payment_status' => PaymentStatus::WrittenOff->value,
        ]);

        Transaction::factory()->create([
            'job_order_id' => $jobOrder->id,
            'type' => TransactionType::DownPayment->value,
            'payment_method' => PaymentMethod::Cash->value,
            'amount' => 300,
            'confirmed_at' => $day->copy()->subDays(20),
            'recorded_by' => $cashier->id,
        ]);

        $receivable = AccountsReceivable::factory()->create([
            'job_order_id' => $jobOrder->id,
            'balance' => 500,
            'status' => AccountsReceivableStatus::Active->value,
            'requested_by' => $cashier->id,
        ]);

        $receivable->forceFill([
            'approved_by' => $admin->id,
            'approved_at' => $day->copy()->subDays(15),
            'due_at' => $day->copy()->subDays(1),
            'write_off_requested_by' => $accounting->id,
            'write_off_requested_at' => $day->copy()->subHours(2),
            'write_off_reason' => 'Customer unreachable after repeated collection attempts; approved for write-off by Admin.',
            'written_off_at' => $day,
        ])->save();
    }

    /**
     * Three active (not written off) On-Credit receivables at different
     * aging brackets -- not required by the Reports registry, but rounds
     * out the demo data for the AR aging pages a reviewer will also see
     * from the Admin/Accounting Staff portals.
     */
    private function seedAgingReceivables(): void
    {
        $admin = $this->accounts['admin'];
        $cashier = $this->accounts['cashier'];

        $brackets = [
            AccountsReceivableAgingBracket::OneToFifteen->value => 10,
            AccountsReceivableAgingBracket::ThirtyOneToSixty->value => 45,
            AccountsReceivableAgingBracket::NinetyPlus->value => 120,
        ];

        foreach ($brackets as $daysPastDue) {
            $approvedAt = now()->subDays($daysPastDue + 30);

            $jobOrder = $this->newJobOrder($approvedAt, [
                'status' => JobOrderStatus::ReadyForPickup->value,
                'total_amount' => 2500,
                'payment_status' => PaymentStatus::OnCredit->value,
            ]);

            $receivable = AccountsReceivable::factory()->create([
                'job_order_id' => $jobOrder->id,
                'balance' => 2500,
                'status' => AccountsReceivableStatus::Active->value,
                'requested_by' => $cashier->id,
            ]);

            $receivable->forceFill([
                'approved_by' => $admin->id,
                'approved_at' => $approvedAt,
                'due_at' => now()->subDays($daysPastDue),
            ])->save();
        }
    }

    /**
     * A queue entry + job order pair for a fresh customer, dated on the
     * given day. Shared by every section above.
     *
     * @param  array<string, mixed>  $jobOrderAttributes
     */
    private function newJobOrder(CarbonInterface $day, array $jobOrderAttributes): JobOrder
    {
        $customer = Customer::factory()->create();

        $queueEntry = QueueEntry::factory()->done()->create([
            'customer_id' => $customer->id,
            'queue_date' => $day->toDateString(),
        ]);

        $attributes = array_merge([
            'queue_entry_id' => $queueEntry->id,
            'type' => JobOrderType::TypeB->value,
        ], $jobOrderAttributes);

        // A priced job order always carries its pricing breakdown: the real
        // POS path snapshots all four columns together, and the receipt
        // prints them. Seeding `total_amount` alone produced receipts
        // reading "Base Price P0.00" above a P3,000 total.
        if (isset($attributes['total_amount']) && ! isset($attributes['base_price_snapshot'])) {
            $total = (float) $attributes['total_amount'];

            // A rush job order is charged the configured rush percentage, so
            // its receipt shows a real fee rather than a waived one. Working
            // backwards from the total keeps `total_amount` -- which other
            // seeded figures (payments, AR balances) are built from -- exactly
            // as written: base + fee still sums to it.
            $rushPercentage = ($attributes['is_rush'] ?? false)
                ? SystemConfiguration::getFloat('rush_fee_percentage', 0.0)
                : 0.0;

            $base = round($total / (1 + ($rushPercentage / 100)), 2);

            $attributes['base_price_snapshot'] = $base;
            $attributes['rush_fee_applied'] = $rushPercentage > 0;
            $attributes['rush_fee_amount'] = round($total - $base, 2);
            $attributes['discount_amount'] = $attributes['discount_amount'] ?? 0;
        }

        return JobOrder::factory()->create($attributes);
    }

    /**
     * The given day-of-month (1-indexed) in the current calendar month, at
     * a fixed mid-morning time -- always in the past relative to "today"
     * for every value this seeder passes in.
     */
    private function dayOfMonth(int $dayOfMonth): CarbonInterface
    {
        return now()->startOfMonth()->addDays($dayOfMonth - 1)->setTime(10, 30);
    }

    /**
     * Today, offset by the given number of days, at start of day -- used
     * for due_at values so urgency (due today-or-earlier) is unambiguous.
     */
    private function dueOffset(int $days): CarbonInterface
    {
        return now()->copy()->startOfDay()->addDays($days);
    }

    /**
     * Print the demo login credentials to the console.
     */
    private function printCredentials(): void
    {
        $this->command->newLine();
        $this->command->info('Demo/UAT accounts (password is the same for all): '.self::DEMO_PASSWORD);

        $rows = collect($this->accounts)
            ->map(fn (User $user): array => [$user->role->value, $user->email, self::DEMO_PASSWORD])
            ->values()
            ->all();

        $this->command->table(['Role', 'Email', 'Password'], $rows);
    }
}
