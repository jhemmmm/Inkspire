export type ChartFormat = 'money' | 'count';

export interface ChartSeries {
    name: string;
    values: number[];
}

export interface BreakdownItem {
    label: string;
    value: number;
}

/** The chart payload ReportBuilder::chart() sends, one shape per form. */
export type ReportChart =
    | {
          type: 'trend';
          title: string;
          format: ChartFormat;
          labels: string[];
          series: ChartSeries[];
      }
    | {
          type: 'breakdown';
          title: string;
          format: ChartFormat;
          items: BreakdownItem[];
      };

/** Full figure, for readouts and tables: ₱12,345.00 or 12. */
export function chartValue(value: number, format: ChartFormat): string {
    return format === 'money'
        ? `₱${value.toLocaleString('en-PH', {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
          })}`
        : value.toLocaleString('en-PH');
}

/** Axis figure: ₱12.5K. */
export function compactChartValue(value: number, format: ChartFormat): string {
    const compact = value.toLocaleString('en-PH', {
        notation: 'compact',
        maximumFractionDigits: 1,
    });

    return format === 'money' ? `₱${compact}` : compact;
}
