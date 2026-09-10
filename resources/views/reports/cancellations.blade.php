@extends('reports.layout')

@section('content')
    @if (empty($rows))
        <p class="empty-state">No records for this range.</p>
    @else
        <table class="report-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Job Order</th>
                    <th>Customer</th>
                    <th class="money">Job Order Total</th>
                    <th class="money">Cancellation Fee</th>
                    <th>Payment Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ \Illuminate\Support\Carbon::parse($row['date'])->format('M j, Y') }}</td>
                        <td>{{ $row['job_order'] }}</td>
                        <td>{{ $row['customer'] }}</td>
                        <td class="money">₱{{ number_format($row['job_order_total'], 2) }}</td>
                        <td class="money">₱{{ number_format($row['cancellation_fee'], 2) }}</td>
                        <td>{{ \Illuminate\Support\Str::headline($row['payment_status']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
