<?php

namespace App\Http\Controllers\Concerns;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Serve a printable document as its print-styled page, or as a PDF download
 * when requested with ?format=pdf. Both are built from the same data.
 */
trait RendersPrintables
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array{0: float, 1: float, 2: float, 3: float}|string  $paper
     */
    protected function printable(Request $request, string $component, string $view, string $filename, array $data, array|string $paper = 'a4'): Response|HttpResponse
    {
        if ($request->query('format') !== 'pdf') {
            return Inertia::render($component, $data + ['pdfUrl' => $request->fullUrlWithQuery(['format' => 'pdf'])]);
        }

        // Same shape the page receives: enums as values, dates as strings.
        $data = json_decode(json_encode($data), true);

        return Pdf::loadView($view, $data)
            ->setPaper($paper)
            ->download($filename.'.pdf');
    }
}
