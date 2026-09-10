@extends('reports.layout')

@section('content')
    @if (empty($rows))
        <p class="empty-state">No records for this range.</p>
    @else
        <table class="report-table">
            <thead>
                <tr>
                    <th>Job Order</th>
                    <th>Customer</th>
                    <th>Product</th>
                    <th>Stage</th>
                    <th>Urgency</th>
                    <th>Entered Production</th>
                    <th>Due</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row['job_order'] }}</td>
                        <td>{{ $row['customer'] }}</td>
                        <td>{{ $row['product'] }}</td>
                        <td>{{ \Illuminate\Support\Str::headline($row['stage']) }}</td>
                        <td>{{ $row['urgency'] ? 'Rush' : 'Normal' }}</td>
                        <td>{{ $row['entered_production']->format('M j, Y') }}</td>
                        <td>{{ $row['due'] !== null ? $row['due']->format('M j, Y') : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
