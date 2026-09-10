@extends('reports.layout')

@section('content')
    @if (empty($rows))
        <p class="empty-state">No records for this range.</p>
    @else
        <table class="report-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th class="money">Amount</th>
                    <th>Recorded By</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ \Illuminate\Support\Carbon::parse($row['date'])->format('M j, Y') }}</td>
                        <td>{{ $row['category'] }}</td>
                        <td>{{ $row['description'] ?? '—' }}</td>
                        <td class="money">₱{{ number_format($row['amount'], 2) }}</td>
                        <td>{{ $row['recorded_by'] }}</td>
                        <td>{{ $row['status'] ?? 'Active' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
