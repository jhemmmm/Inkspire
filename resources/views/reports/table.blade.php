@extends('reports.layout')

@section('content')
    @if (empty($rows))
        <p class="empty-state">No records for this range.</p>
    @else
        <table class="report-table">
            <thead>
                <tr>
                    @foreach ($headings as $index => $heading)
                        <th @class(['money' => in_array($index, $moneyColumns, true)])>{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @foreach ($row as $index => $value)
                            <td @class(['money' => in_array($index, $moneyColumns, true)])>
                                @if ($value === null)
                                    —
                                @elseif ($value instanceof \Carbon\CarbonInterface)
                                    {{ $value->format('M j, Y') }}
                                @elseif (in_array($index, $moneyColumns, true))
                                    ₱{{ number_format((float) $value, 2) }}
                                @else
                                    {{ $value }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                @if ($totalRow !== null)
                    <tr>
                        @foreach ($totalRow as $index => $value)
                            <td @class(['money' => in_array($index, $moneyColumns, true)])>
                                <strong>
                                    @if ($value === null)
                                        {{-- blank, not dashed --}}
                                    @elseif ($value instanceof \Carbon\CarbonInterface)
                                        {{ $value->format('M j, Y') }}
                                    @elseif (in_array($index, $moneyColumns, true))
                                        ₱{{ number_format((float) $value, 2) }}
                                    @else
                                        {{ $value }}
                                    @endif
                                </strong>
                            </td>
                        @endforeach
                    </tr>
                @endif
            </tbody>
        </table>
    @endif
@endsection
