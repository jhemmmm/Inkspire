<?php

use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\JobOrder;
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

    // On-screen preview caps at 100.
    $onScreen = $this->actingAs($cashier)->withHeaders(reportExportHeaders())->get(route('cashier.reports.index', ['report' => 'sales']));
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
