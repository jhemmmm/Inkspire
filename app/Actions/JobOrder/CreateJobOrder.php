<?php

namespace App\Actions\JobOrder;

use App\Actions\POS\QuoteJobOrderLineAmount;
use App\Enums\FileValidationOutcome;
use App\Enums\JobOrderStatus;
use App\Enums\JobOrderType;
use App\Models\JobOrder;
use App\Models\PricingEntry;
use App\Models\QueueEntry;
use Illuminate\Http\UploadedFile;

/**
 * Create one job order on a visit and apply its intake outcome.
 *
 * Callers MUST invoke this inside an enclosing `DB::transaction`:
 * JobOrder::nextNumberForYear() opens its own transaction, so without an
 * outer one its lockForUpdate() range lock is released before the insert
 * runs and two concurrent callers can compute the same number (SQLite makes
 * lockForUpdate() a no-op, so no test catches it).
 */
class CreateJobOrder
{
    public function __construct(
        public ValidateJobOrderFile $validateJobOrderFile,
        public EnterProduction $enterProduction,
        public QuoteJobOrderLineAmount $quoteJobOrderLineAmount,
    ) {}

    /**
     * A row carries EITHER `file` (an UploadedFile, stored and validated
     * here) OR `file_path` + `file_check` (a trusted, server-produced
     * `['outcome' => FileValidationOutcome|string, 'reason' => ?string]`
     * from a file that was already stored and checked elsewhere).
     *
     * @param  array<string, mixed>  $row
     */
    public function __invoke(QueueEntry $entry, array $row): JobOrder
    {
        $file = ($row['file'] ?? null) instanceof UploadedFile ? $row['file'] : null;

        $jobOrder = $entry->jobOrders()->create([
            'number' => JobOrder::nextNumberForYear(JobOrder::currentNumberingYear()),
            'description' => $row['description'],
            'print_size' => $row['print_size'] ?? null,
            'quantity' => $row['quantity'] ?? null,
            'width_ft' => $row['width_ft'] ?? null,
            'height_ft' => $row['height_ft'] ?? null,
            'quoted_amount' => $this->quoteAmountForRow($row),
            'deadline' => $row['deadline'] ?? null,
            // filter_var, not a bare cast: the FormData path delivers
            // the string "1"/"0" while a JSON payload delivers a real
            // boolean, and both must land as the same column value.
            'is_rush' => filter_var($row['is_rush'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'client_notes' => $row['client_notes'] ?? null,
            'pricing_entry_id' => $row['pricing_entry_id'] ?? null,
            'type' => $row['type'],
            'status' => JobOrderStatus::Intake,
            'file_path' => $file?->store('job-orders', 'local') ?? ($row['file_path'] ?? null),
        ]);

        $this->applyIntakeOutcome($jobOrder, $file, $row['file_check'] ?? null);

        return $jobOrder;
    }

    /**
     * The `quoted_amount` to store for a validated job order row: null with
     * no service picked, the staff override when one was sent, otherwise the
     * catalog quote (D-01). Quantity defaults to 1 for pricing only.
     *
     * @param  array<string, mixed>  $row
     */
    private function quoteAmountForRow(array $row): ?float
    {
        if (($row['pricing_entry_id'] ?? null) === null) {
            return null;
        }

        if (($row['quoted_amount'] ?? '') !== '') {
            return (float) $row['quoted_amount'];
        }

        $pricingEntry = PricingEntry::find((int) $row['pricing_entry_id'], ['id', 'base_price', 'unit']);

        if ($pricingEntry === null) {
            return null;
        }

        return ($this->quoteJobOrderLineAmount)(
            $pricingEntry,
            isset($row['width_ft']) ? (float) $row['width_ft'] : null,
            isset($row['height_ft']) ? (float) $row['height_ft'] : null,
            (int) ($row['quantity'] ?? 1),
        );
    }

    /**
     * Apply the correct auto-outcome for a freshly created job order
     * (JOB-01/JOB-02) — Type A gets its file validated. Type B is
     * deliberately left at Intake with no artist: job orders are pulled
     * from a shared pool by whichever available Artist accepts them, not
     * pushed onto one at intake.
     *
     * @param  array{outcome: FileValidationOutcome|string, reason?: ?string}|null  $fileCheck
     */
    private function applyIntakeOutcome(JobOrder $jobOrder, ?UploadedFile $file, ?array $fileCheck): void
    {
        if ($jobOrder->type !== JobOrderType::TypeA) {
            return;
        }

        if ($fileCheck !== null) {
            $outcome = $fileCheck['outcome'] instanceof FileValidationOutcome
                ? $fileCheck['outcome']
                : FileValidationOutcome::from($fileCheck['outcome']);
            $reason = $fileCheck['reason'] ?? null;
        } else {
            $result = ($this->validateJobOrderFile)(
                $file,
                $jobOrder->print_size,
                $jobOrder->width_ft !== null ? (float) $jobOrder->width_ft : null,
                $jobOrder->height_ft !== null ? (float) $jobOrder->height_ft : null,
            );
            $outcome = $result['outcome'];
            $reason = $result['reason'];
        }

        // NeedsArtist lands on Intake — the same shared pool a Type B waits
        // in — so any available Artist can pull it. The failure reason rides
        // along as the brief: it says exactly what is wrong with the file.
        $jobOrder->forceFill([
            'status' => match ($outcome) {
                FileValidationOutcome::Passed => JobOrderStatus::ReadyForProduction,
                FileValidationOutcome::NeedsArtist => JobOrderStatus::Intake,
                FileValidationOutcome::Rejected => JobOrderStatus::ValidationFailed,
            },
            'validation_failure_reason' => $reason,
        ])->save();

        if ($outcome === FileValidationOutcome::Passed) {
            ($this->enterProduction)($jobOrder);
        }
    }
}
