@extends('reports.layout')

@section('content')
    <p>{{ $today }}</p>
    <p>To: {{ $customerName ?? '—' }}</p>
    <p>Re: Job Order {{ $jobOrderNumber ?? '—' }} &mdash; {{ $jobOrderDescription }}</p>

    <p>{{ $letterBodyText }}</p>

    <table class="report-table" style="margin-top: 16px;">
        <tbody>
            <tr>
                <td>Credit Extended</td>
                <td class="money">₱{{ number_format($creditExtended, 2) }}</td>
            </tr>
            <tr>
                <td>Amount Paid</td>
                <td class="money">₱{{ number_format($amountPaid, 2) }}</td>
            </tr>
            <tr>
                <td><strong>Amount Due</strong></td>
                <td class="money"><strong>₱{{ number_format($amountDue, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <p class="meta-line">Due Date: {{ $dueDateLabel }} &middot; {{ $daysPastDue ?? 0 }} days past due</p>

    <p>
        Payments are accepted at our counter during business hours. Please
        bring this notice or quote Job Order {{ $jobOrderNumber ?? '—' }}.
    </p>

    <p style="margin-top: 24px;">Sincerely,</p>
    <p>Accounts Receivable<br>{{ config('app.name') }}</p>

    <p class="meta-line">Printed {{ $today }} &middot; Job Order {{ $jobOrderNumber ?? '—' }}</p>
@endsection
