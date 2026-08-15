<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    public function localizedName(?string $locale = null): string
    {
        return app(\App\Services\ApplicationDocumentRequestService::class)
            ->localizedDocumentTypeName($this->code, $this->name, $locale);
    }
}
