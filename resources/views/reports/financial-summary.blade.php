@extends('reports.layout')

@section('content')
    <table class="report-table">
        <thead>
            <tr>
                <th>Label</th>
                <th class="money">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Job Sales</td>
                <td class="money">₱{{ number_format($summary['job_sales'], 2) }}</td>
            </tr>
            <tr>
                <td>Cancellation Fees</td>
                <td class="money">₱{{ number_format($summary['cancellation_fees'], 2) }}</td>
            </tr>
            <tr>
                <td><strong>Total Revenue</strong></td>
                <td class="money"><strong>₱{{ number_format($summary['revenue_total'], 2) }}</strong></td>
            </tr>
            <tr>
                <td>Recorded Expenses</td>
                <td class="money">₱{{ number_format($summary['expenses_total'], 2) }}</td>
            </tr>
            <tr>
                <td><strong>{{ $summary['result'] < 0 ? 'Net Loss' : 'Net Profit' }}</strong></td>
                <td class="money">
                    <strong>{{ $summary['result'] < 0 ? '-' : '' }}₱{{ number_format(abs($summary['result']), 2) }}</strong>
                </td>
            </tr>
        </tbody>
    </table>

    @if ($summary['write_off_total'] > 0)
        <p class="meta-line" style="margin-top: 16px;">
            Bad debt written off: ₱{{ number_format($summary['write_off_total'], 2) }} &mdash; not deducted above (never counted as revenue, so never subtracted from it).
        </p>
    @endif
@endsection
