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
                    <th>Type</th>
                    <th>Method</th>
                    <th class="money">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ \Illuminate\Support\Carbon::parse($row['date'])->format('M j, Y') }}</td>
                        <td>{{ $row['job_order'] }}</td>
                        <td>{{ $row['customer'] }}</td>
                        <td>{{ \Illuminate\Support\Str::headline($row['type']) }}</td>
                        <td>{{ \Illuminate\Support\Str::headline($row['method']) }}</td>
                        <td class="money">₱{{ number_format($row['amount'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
