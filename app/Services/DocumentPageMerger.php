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

        return $this->mergeTo($valid, "customer/{$customerId}/documents", $basename);
    }

    /**
     * Combine pages into one stored file under an arbitrary public-disk directory.
     *
     * @param  list<UploadedFile>  $files
     */
    public function mergeTo(array $files, string $directory, string $basename = 'document'): string
    {
        $valid = array_values(array_filter(
            $files,
            fn ($file) => $file instanceof UploadedFile && $file->isValid()
        ));

        if ($valid === []) {
            throw new \InvalidArgumentException('No valid files to merge.');
        }

        $directory = trim($directory, '/');

        if (count($valid) === 1) {
            $file = $valid[0];
            $mime = $file->getMimeType() ?? '';

            if ($mime === 'application/pdf') {
                return $file->store($directory, 'public');
            }

            return $this->imagesToPdf($valid, $directory, $basename);
        }

        return $this->imagesToPdf($valid, $directory, $basename);
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    private function imagesToPdf(array $files, string $directory, string $basename): string
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
        $path = $directory.'/'.$filename;
        Storage::disk('public')->put($path, Pdf::loadHTML($html)->setPaper('a4', 'portrait')->output());

        return $path;
    }
}
