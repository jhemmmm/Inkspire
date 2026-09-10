<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 18mm;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9pt;
            line-height: 1.4;
            color: #000;
        }

        .letterhead {
            font-size: 18pt;
            font-weight: 700;
        }

        .document-title {
            font-size: 14pt;
            font-weight: 700;
            margin-top: 4px;
        }

        .meta-line {
            font-size: 9pt;
            color: #333;
            margin-top: 2px;
        }

        .header-rule {
            border: none;
            border-top: 1px solid #dddddd;
            margin: 12px 0 16px 0;
        }

        table.report-table {
            width: 100%;
            border-collapse: collapse;
        }

        table.report-table thead th {
            background-color: #f5f5f5;
            border: 1px solid #dddddd;
            padding: 6px 8px;
            text-align: left;
            font-weight: 700;
        }

        table.report-table tbody td {
            border: 1px solid #dddddd;
            padding: 6px 8px;
            page-break-inside: avoid;
        }

        table.report-table tbody tr {
            page-break-inside: avoid;
        }

        table.report-table td.money,
        table.report-table th.money {
            text-align: right;
        }

        .empty-state {
            text-align: center;
            color: #666;
            padding: 24px 0;
        }
    </style>
</head>
<body>
    <div class="letterhead">{{ config('app.name') }}</div>
    <div class="document-title">{{ $title }}</div>
    @isset($from, $to)
        <div class="meta-line">Range: {{ $from->format('M j, Y') }} &ndash; {{ $to->format('M j, Y') }}</div>
    @endisset
    @isset($generatedAt, $generatedBy)
        <div class="meta-line">Generated {{ $generatedAt->format('M j, Y g:i A') }} by {{ $generatedBy }}</div>
    @endisset
    <hr class="header-rule">

    @yield('content')

    <script type="text/php">
    if (isset($pdf)) {
        $text = "{{ addslashes(config('app.name')) }} \xC2\xB7 {{ addslashes($title) }} \xC2\xB7 Page {$PAGE_NUM} of {$PAGE_COUNT}";
        $font = $fontMetrics->getFont('DejaVu Sans', 'normal');
        $size = 8;
        $width = $fontMetrics->getTextWidth($text, $font, $size);
        $x = ($pdf->get_width() - $width) / 2;
        $y = $pdf->get_height() - 24;
        $pdf->page_text($x, $y, $text, $font, $size, [0.4, 0.4, 0.4]);
    }
    </script>
</body>
</html>
