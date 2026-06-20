<?php

namespace App\Models;

use App\Models\Concerns\MapsLegacyPartnerId;
use App\Models\Concerns\MapsLegacyPartnerTaskId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerDocument extends Model
{
    use MapsLegacyPartnerId;
    use MapsLegacyPartnerTaskId;

    protected $table = 'partner_documents';

    protected $guarded = [];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->partner();
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(PartnerTask::class, 'partner_task_id');
    }
}
