<?php

namespace App\Support\Dashboard;

use App\Models\AuditLog;
use App\Models\Institution;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * A dashboard flattened into titled tables, downloadable as CSV or PDF.
 * Both formats carry the same rows and the filters they were built with.
 */
class DashboardExport
{
    /** @var list<array{title: string, columns: list<string>, rows: list<list<string|int|float|null>>}> */
    private array $tables = [];

    /**
     * @param  array<string, string>  $meta  label => value lines (scope, session, range…)
     */
    public function __construct(
        private readonly string $title,
        private readonly array $meta = [],
    ) {}

    /**
     * @param  list<string>  $columns
     * @param  iterable<list<string|int|float|null>>  $rows
     */
    public function table(string $title, array $columns, iterable $rows): self
    {
        $this->tables[] = ['title' => $title, 'columns' => $columns, 'rows' => collect($rows)->values()->all()];

        return $this;
    }

    /**
     * A two-column table from chart data ([['label' => …, 'value' => …], …]).
     *
     * @param  iterable<array{label: string, value: int|float}>  $data
     */
    public function series(string $title, string $labelColumn, string $valueColumn, iterable $data): self
    {
        return $this->table($title, [$labelColumn, $valueColumn], collect($data)->map(fn ($d) => [$d['label'], $d['value']]));
    }

    public function download(string $format): Response
    {
        $filename = Str::slug($this->title).'-'.now()->format('Y-m-d');

        AuditLog::record('dashboard.exported', 'Dashboard', null, ['format' => $format] + $this->meta, "{$this->title} exported as ".strtoupper($format));

        if ($format === 'pdf') {
            return Pdf::loadView('pdf.dashboard', [
                'institution' => Institution::current()?->only('name', 'motto', 'address'),
                'title' => $this->title,
                'meta' => $this->meta,
                'tables' => $this->tables,
                'generatedAt' => now()->format('j M Y, g:i a'),
            ])
                // Embed only the glyphs used; the full DejaVu font is over 1 MB.
                ->setOption('enable_font_subsetting', true)
                ->setPaper('a4')
                ->download($filename.'.pdf');
        }

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, [$this->title]);
            foreach ($this->meta as $label => $value) {
                fputcsv($out, [$label, $value]);
            }

            foreach ($this->tables as $table) {
                fputcsv($out, []);
                fputcsv($out, [$table['title']]);
                fputcsv($out, $table['columns']);
                foreach ($table['rows'] as $row) {
                    fputcsv($out, $row);
                }
            }

            fclose($out);
        }, $filename.'.csv', ['Content-Type' => 'text/csv']);
    }
}
