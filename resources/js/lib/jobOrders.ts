/**
 * Shared job order presentation, so a label is changed in one place rather
 * than in every page that renders it.
 */
const JOB_ORDER_TYPE_LABELS: Record<string, string> = {
    type_a: 'Type A',
    type_b: 'Type B',
};

export function jobOrderTypeLabel(type: string): string {
    return JOB_ORDER_TYPE_LABELS[type] ?? type;
}

/**
 * Every `App\Enums\JobOrderStatus` case. Pages that only ever see a subset
 * keep their own narrower helper — this one is for views like the counter's
 * job order lookup, which can land on any stage in the system.
 */
const JOB_ORDER_STATUS_LABELS: Record<string, string> = {
    intake: 'Waiting for an Artist',
    validation_failed: 'Validation Failed',
    ready_for_production: 'Ready for Production',
    assigned: 'Assigned to an Artist',
    in_consultation: 'In Consultation',
    in_design: 'In Design',
    pending_review: 'Awaiting Customer Approval',
    design_approved: 'Design Approved',
    for_production: 'For Production',
    printing: 'Printing',
    quality_check: 'Quality Check',
    ready_for_pickup: 'Ready for Pickup',
};

export function jobOrderStatusLabel(status: string): string {
    return JOB_ORDER_STATUS_LABELS[status] ?? status.replaceAll('_', ' ');
}

const PAYMENT_STATUS_LABELS: Record<string, string> = {
    unpaid: 'Unpaid',
    partially_paid: 'Partially Paid',
    pending_confirmation: 'Pending Confirmation',
    paid: 'Paid',
    credit_pending_approval: 'Credit Pending Approval',
    on_credit: 'On Credit',
    credit_rejected: 'Credit Rejected',
    written_off: 'Written Off',
};

export function paymentStatusLabel(status: string): string {
    return PAYMENT_STATUS_LABELS[status] ?? status.replaceAll('_', ' ');
}

const PAYMENT_METHOD_LABELS: Record<string, string> = {
    cash: 'Cash',
    bank_transfer: 'Bank Transfer',
    gcash: 'GCash',
    maya: 'Maya',
};

export function paymentMethodLabel(method: string | null): string {
    return method === null ? '—' : (PAYMENT_METHOD_LABELS[method] ?? method);
}

/**
 * Peso, grouped, always two decimals. Grouping matters more than it looks:
 * ₱24300.00 and ₱243000.00 are a glance apart without it.
 */
const peso = new Intl.NumberFormat('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

export function money(value: number | null): string {
    return `₱${peso.format(Number(value ?? 0))}`;
}

/**
 * Compute the intake-time line amount for a job order row, mirroring
 * `App\Actions\POS\QuoteJobOrderLineAmount` exactly (same "sq ft" prefix
 * check, same null-if-missing-dimensions rule, same rounding to 2dp) — used
 * for the live price preview only. The server recomputes on submit either
 * way, so a tampered client total only ever matters as the explicit
 * override field.
 */
export function round2(value: number): number {
    return Math.round(value * 100) / 100;
}

export function isSqFtUnit(unit: string | null | undefined): boolean {
    return (unit ?? '').toLowerCase().startsWith('sq ft');
}

export function quoteLineAmount(
    entry: { base_price: string; unit: string | null } | undefined,
    widthFt: number | null,
    heightFt: number | null,
    quantity: number,
): number | null {
    if (!entry) {
        return null;
    }

    const basePrice = Number(entry.base_price);

    if (isSqFtUnit(entry.unit)) {
        if (widthFt === null || heightFt === null) {
            return null;
        }

        return round2(basePrice * widthFt * heightFt * quantity);
    }

    return round2(basePrice * quantity);
}

/**
 * Blank or non-numeric form input reads as null. `<Input type="number">`
 * hands back a number once edited, so both shapes are accepted.
 */
export function parseNumber(
    value: string | number | null | undefined,
): number | null {
    if (value === null || value === undefined || String(value).trim() === '') {
        return null;
    }

    const parsed = Number(value);

    return Number.isFinite(parsed) ? parsed : null;
}

/** Quantity defaults to 1 when blank or not positive, matching the server. */
export function parseQuantity(
    value: string | number | null | undefined,
): number {
    const parsed = parseNumber(value);

    return parsed !== null && parsed > 0 ? parsed : 1;
}

/**
 * The line amount a job order row will be saved with: the staff override
 * when one is set, otherwise the quote from the row's service and size.
 */
export function rowLineAmount(
    row: {
        pricing_entry_id: string;
        width_ft: string | number;
        height_ft: string | number;
        quantity: string | number;
        quoted_amount: string | number;
        quoted_amount_overridden: boolean;
    },
    entries: { id: number; base_price: string; unit: string | null }[],
): number | null {
    if (row.quoted_amount_overridden) {
        return parseNumber(row.quoted_amount);
    }

    return quoteLineAmount(
        entries.find((entry) => String(entry.id) === row.pricing_entry_id),
        parseNumber(row.width_ft),
        parseNumber(row.height_ft),
        parseQuantity(row.quantity),
    );
}

/** Mirrors ComputeJobOrderPrice's rush fee. */
export function rushFee(lineAmount: number, rushFeePercentage: number): number {
    return round2((lineAmount * rushFeePercentage) / 100);
}

/** What is still owed against a table row's total, or `—` when unpriced. */
export function balanceLabel(jobOrder: {
    display_total: number | null;
    amount_paid: number | null;
}): string {
    return jobOrder.display_total === null
        ? '—'
        : money(
              Math.max(0, jobOrder.display_total - (jobOrder.amount_paid ?? 0)),
          );
}
