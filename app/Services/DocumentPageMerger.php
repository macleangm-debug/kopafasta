<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentPageMerger
{
    /**
     * Combine one or more image/PDF pages into a single stored file (PDF when multiple images).
     *
     * @param  list<UploadedFile>  $files
     */
    public function merge(array $files, int $customerId, string $basename = 'document'): string
    {
        $valid = array_values(array_filter(
            $files,
            fn ($file) => $file instanceof UploadedFile && $file->isValid()
        ));

        if ($valid === []) {
            throw new \InvalidArgumentException('No valid files to merge.');
        }

        if (count($valid) === 1) {
            $file = $valid[0];
            $mime = $file->getMimeType() ?? '';

            if ($mime === 'application/pdf') {
                return $file->store("customer/{$customerId}/documents", 'public');
            }

            return $this->imagesToPdf($valid, $customerId, $basename);
        }

        $singlePdf = collect($valid)->first(
            fn (UploadedFile $file) => ($file->getMimeType() ?? '') === 'application/pdf'
        );

        if ($singlePdf && count($valid) === 1) {
            return $singlePdf->store("customer/{$customerId}/documents", 'public');
        }

        return $this->imagesToPdf($valid, $customerId, $basename);
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    private function imagesToPdf(array $files, int $customerId, string $basename): string
    {
        $html = '<html><body style="margin:0;padding:0;">';

        foreach ($files as $file) {
            $mime = $file->getMimeType() ?? 'image/jpeg';
            $contents = base64_encode(file_get_contents($file->getRealPath()));
            $html .= '<div style="page-break-after:always;text-align:center;">';
            $html .= '<img src="data:'.$mime.';base64,'.$contents.'" style="max-width:100%;height:auto;">';
            $html .= '</div>';
        }

        $html .= '</body></html>';

        $filename = Str::slug($basename).'-'.Str::uuid().'.pdf';
        $path = "customer/{$customerId}/documents/{$filename}";
        Storage::disk('public')->put($path, Pdf::loadHTML($html)->setPaper('a4', 'portrait')->output());

        return $path;
    }
}
