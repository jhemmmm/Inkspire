<?php

use App\Enums\JobOrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TransactionType;
use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\JobOrder;
use App\Models\ProductionLog;
use App\Models\Transaction;
use App\Models\User;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Matches Inertia\Middleware::version()'s default resolver exactly, so a
 * test-issued X-Inertia-Version header matches what the real middleware
 * computes for this request and avoids a spurious 409 version conflict.
 */
function reportExportHeaders(): array
{
    $manifest = public_path('build/manifest.json');

    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => file_exists($manifest) ? hash_file('xxh128', $manifest) : null,
    ];
}

/**
 * Reads a streamed xlsx TestResponse's content back into row arrays via
 * openspout's own reader -- the most direct way to assert cell contents
 * without hand-parsing OOXML. Only called from tests already guarded by
 * skipUnlessZipAvailable().
 *
 * @return list<list<mixed>>
 */
function readXlsxRows(string $content): array
{
    $path = tempnam(sys_get_temp_dir(), 'xlsx-test-').'.xlsx';
    file_put_contents($path, $content);

    $reader = new Reader;
    $reader->open($path);

    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }

    $reader->close();
    unlink($path);

    return $rows;
}

test('exporting sales to PDF for an entitled cashier returns a real PDF and audits exactly one row (D-02)', function () {
    $cashier = User::factory()->cashier()->create();
    Transaction::factory()->for(JobOrder::factory()->create(['total_amount' => 1000]))->create();

    expect(AuditLog::where('action', 'report_exported')->count())->toBe(0);

    $response = $this->actingAs($cashier)->get(route('cashier.reports.export.pdf', 'sales'));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    expect(AuditLog::where('action', 'report_exported')->count())->toBe(1);

    $audit = AuditLog::where('action', 'report_exported')->sole();
    expect($audit->user_id)->toBe($cashier->id);
    expect($audit->new_values['report'])->toBe('sales');
    expect($audit->new_values['format'])->toBe('pdf');
});

test('exporting expenses to PDF for an entitled accounting staff returns a real PDF and audits exactly one row (D-02)', function () {
    $accountingStaff = User::factory()->accountingStaff()->create();
    Expense::factory()->create(['expense_date' => now()]);

    $response = $this->actingAs($accountingStaff)->get(route('accounting-staff.reports.export.pdf', 'expenses'));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');

    $audit = AuditLog::where('action', 'report_exported')->sole();
    expect($audit->new_values['report'])->toBe('expenses');
    expect($audit->new_values['format'])->toBe('pdf');
});

test('exporting sales to xlsx for an entitled cashier returns a real xlsx and audits exactly one row (D-02)', function () {
    $this->skipUnlessZipAvailable();

    $cashier = User::factory()->cashier()->create();
    Transaction::factory()->for(JobOrder::factory()->create(['total_amount' => 1000]))->create();

    $from = now()->startOfMonth()->toDateString();
    $to = now()->endOfDay()->toDateString();

    $response = $this->actingAs($cashier)->get(route('cashier.reports.export.xlsx', 'sales'));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $response->assertHeader('Content-Disposition', "attachment; filename=sales_{$from}_{$to}.xlsx");

    $audit = AuditLog::where('action', 'report_exported')->sole();
    expect($audit->new_values['report'])->toBe('sales');
    expect($audit->new_values['format'])->toBe('xlsx');
});

test('exporting financial-summary to xlsx for an entitled accounting staff returns a real xlsx and audits exactly one row (D-02)', function () {
    $this->skipUnlessZipAvailable();

    $accountingStaff = User::factory()->accountingStaff()->create();
    Expense::factory()->create(['amount' => 50, 'expense_date' => now()]);

    $response = $this->actingAs($accountingStaff)->get(route('accounting-staff.reports.export.xlsx', 'financial-summary'));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $rows = readXlsxRows($response->streamedContent());

    expect($rows[0])->toBe(['Label', 'Amount']);
    expect($rows[1][0])->toBe('Job Sales');
    // Raw numeric values, no currency symbol / thousands separator (binding data contract).
    expect($rows[4][0])->toBe('Recorded Expenses');
    expect((float) $rows[4][1])->toBe(50.0);

    $audit = AuditLog::where('action', 'report_exported')->sole();
    expect($audit->new_values['report'])->toBe('financial-summary');
    expect($audit->new_values['format'])->toBe('xlsx');
});

test('xlsx export of a non-financial-summary report is not capped at 100 rows, unlike the on-screen preview', function () {
    $this->skipUnlessZipAvailable();

    $cashier = User::factory()->cashier()->create();
    Transaction::factory()->count(150)->for(JobOrder::factory()->create(['total_amount' => 1000]))->create();

    // On-screen preview caps at 100. Headers are passed per-call (not via
    // withHeaders()) so the X-Inertia header doesn't leak into the xlsx
    // request below -- Inertia\Middleware::handle() treats a GET request
    // that carries X-Inertia but gets back an unsent StreamedResponse
    // (empty getContent()) as a stale/empty Inertia response and redirects
    // it with Redirect::back(), which withHeaders()'s persisted header
    // would otherwise trigger on a real file-download response.
    $onScreen = $this->actingAs($cashier)->get(route('cashier.reports.index', ['report' => 'sales']), reportExportHeaders());
    $onScreen->assertOk();
    expect($onScreen->json('props.rows'))->toHaveCount(100);
    expect($onScreen->json('props.rowsTotal'))->toBe(150);

    // The xlsx export is not.
    $response = $this->actingAs($cashier)->get(route('cashier.reports.export.xlsx', 'sales'));
    $response->assertOk();

    $rows = readXlsxRows($response->streamedContent());

    // Header row + 150 data rows + Total row.
    expect($rows)->toHaveCount(152);
});

test('export pdf denied for a role not entitled to that report key returns 403 and writes zero audit rows', function () {
    $cashier = User::factory()->cashier()->create();

    $response = $this->actingAs($cashier)->get(route('cashier.reports.export.pdf', 'expenses'));

    $response->assertForbidden();
    expect(AuditLog::where('action', 'report_exported')->count())->toBe(0);
});

test('export xlsx denied for a role not entitled to that report key returns 403 and writes zero audit rows', function () {
    // This denial happens before the writer is ever invoked, so it needs
    // no ZipArchive guard and must run on every environment (T-08-13).
    $cashier = User::factory()->cashier()->create();

    $response = $this->actingAs($cashier)->get(route('cashier.reports.export.xlsx', 'expenses'));

    $response->assertForbidden();
    expect(AuditLog::where('action', 'report_exported')->count())->toBe(0);
});

test('export xlsx denied for a second role/report combination not entitled (production staff on financial-summary)', function () {
    $productionStaff = User::factory()->productionStaff()->create();

    $response = $this->actingAs($productionStaff)->get(route('production-staff.reports.export.xlsx', 'financial-summary'));

    $response->assertForbidden();
    expect(AuditLog::where('action', 'report_exported')->count())->toBe(0);
});

test('viewing the on-screen report writes zero audit_trail rows -- only export does', function () {
    $cashier = User::factory()->cashier()->create();

    $this->actingAs($cashier)->withHeaders(reportExportHeaders())->get(route('cashier.reports.index', ['report' => 'sales']))
        ->assertOk();

    expect(AuditLog::where('action', 'report_exported')->count())->toBe(0);
});

test('the xlsx expenses Total row excludes voided expenses (mirrors Expense::active())', function () {
    $this->skipUnlessZipAvailable();

    $accountingStaff = User::factory()->accountingStaff()->create();

    Expense::factory()->create(['amount' => 1000, 'expense_date' => now()]);
    Expense::factory()->create(['amount' => 500, 'expense_date' => now()]);
    Expense::factory()->voided()->create(['amount' => 250, 'expense_date' => now()]);

    $response = $this->actingAs($accountingStaff)->get(route('accounting-staff.reports.export.xlsx', 'expenses'));

    $response->assertOk();

    $rows = readXlsxRows($response->streamedContent());
    $totalRow = end($rows);

    expect($totalRow[0])->toBe('Total');
    // 1000 + 500, with the voided 250 excluded -- a voided expense stays
    // visible as its own row but must never be summed into the Total.
    expect((float) $totalRow[3])->toBe(1500.0);
    // The voided row is still present in the sheet, just not in the Total.
    expect(count($rows))->toBe(5); // header + 3 expenses + total
});

test('exporting sales to xlsx resolves type and method to the same labels the PDF shows (export parity)', function () {
    $this->skipUnlessZipAvailable();

    $cashier = User::factory()->cashier()->create();
    Transaction::factory()->for(JobOrder::factory()->create(['total_amount' => 1000]))->create(['type' => TransactionType::DownPayment, 'payment_method' => PaymentMethod::Gcash]);

    $response = $this->actingAs($cashier)->get(route('cashier.reports.export.xlsx', 'sales'));

    $response->assertOk();

    $rows = readXlsxRows($response->streamedContent());

    expect($rows[1][3])->toBe('Down Payment');
    expect($rows[1][4])->toBe('Gcash');
});

test('exporting cancellations to xlsx resolves payment status to the same label the PDF shows (export parity)', function () {
    $this->skipUnlessZipAvailable();

    $cashier = User::factory()->cashier()->create();
    JobOrder::factory()->create(['cancelled_at' => now(), 'payment_status' => PaymentStatus::PartiallyPaid]);

    $response = $this->actingAs($cashier)->get(route('cashier.reports.export.xlsx', 'cancellations'));

    $response->assertOk();

    $rows = readXlsxRows($response->streamedContent());

    expect($rows[1][5])->toBe('Partially Paid');
});

test('exporting production-status to xlsx resolves urgency and stage to the same labels the PDF shows (export parity)', function () {
    $this->skipUnlessZipAvailable();

    $productionStaff = User::factory()->productionStaff()->create();

    $rush = JobOrder::factory()->create(['status' => JobOrderStatus::ForProduction->value]);
    $rush->forceFill(['due_at' => now()->subDay()])->save();
    ProductionLog::factory()->for($rush)->create(['to_status' => JobOrderStatus::ForProduction->value, 'created_at' => now()]);

    $normal = JobOrder::factory()->create(['status' => JobOrderStatus::ForProduction->value]);
    $normal->forceFill(['due_at' => now()->addWeek()])->save();
    ProductionLog::factory()->for($normal)->create(['to_status' => JobOrderStatus::ForProduction->value, 'created_at' => now()]);

    $response = $this->actingAs($productionStaff)->get(route('production-staff.reports.export.xlsx', 'production-status'));

    $response->assertOk();

    $rows = readXlsxRows($response->streamedContent());

    expect([$rows[1][4], $rows[2][4]])
        ->toContain('Rush')
        ->toContain('Normal');
    expect($rows[1][3])->toBe('For Production');
    expect($rows[2][3])->toBe('For Production');
});

test('exporting expenses to xlsx resolves an active expense\'s status to "Active" (export parity)', function () {
    $this->skipUnlessZipAvailable();

    $accountingStaff = User::factory()->accountingStaff()->create();
    Expense::factory()->create(['expense_date' => now()]);

    $response = $this->actingAs($accountingStaff)->get(route('accounting-staff.reports.export.xlsx', 'expenses'));

    $response->assertOk();

    $rows = readXlsxRows($response->streamedContent());

    expect($rows[1][5])->toBe('Active');
});

test('exporting production-status to PDF renders through the generic table view in place of the deleted per-report Blade view', function () {
    $productionStaff = User::factory()->productionStaff()->create();

    $jobOrder = JobOrder::factory()->create(['status' => JobOrderStatus::ForProduction->value]);
    ProductionLog::factory()->for($jobOrder)->create(['to_status' => JobOrderStatus::ForProduction->value, 'created_at' => now()]);

    $response = $this->actingAs($productionStaff)->get(route('production-staff.reports.export.pdf', 'production-status'));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
});
