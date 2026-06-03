<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class ResidenceLetterMerger
{
    public function __construct(private DocumentPageMerger $merger) {}

    /**
     * @param  list<UploadedFile>  $files
     */
    public function merge(array $files, int $customerId): string
    {
        return $this->merger->merge($files, $customerId, 'residence-letter');
    }
}
