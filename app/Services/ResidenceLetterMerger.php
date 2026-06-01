<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ResidenceLetterMerger
{
    /**
     * Combine one or more image/PDF pages into a single PDF document.
     *
     * @param  list<UploadedFile>  $files
     */
    public function merge(array $files, int $customerId): string
    {
        $html = '<html><body style="margin:0;padding:0;">';

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $mime = $file->getMimeType() ?? '';
            if ($mime === 'application/pdf') {
                $stored = $file->store("customer/{$customerId}/documents/tmp", 'public');

                return $stored;
            }

            $contents = base64_encode(file_get_contents($file->getRealPath()));
            $html .= '<div style="page-break-after:always;text-align:center;">';
            $html .= '<img src="data:'.$mime.';base64,'.$contents.'" style="max-width:100%;height:auto;">';
            $html .= '</div>';
        }

        $html .= '</body></html>';

        $filename = 'residence-letter-'.Str::uuid().'.pdf';
        $path = "customer/{$customerId}/documents/{$filename}";
        Storage::disk('public')->put($path, Pdf::loadHTML($html)->setPaper('a4', 'portrait')->output());

        return $path;
    }
}
