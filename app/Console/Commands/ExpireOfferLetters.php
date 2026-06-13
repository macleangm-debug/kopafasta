<?php

namespace App\Console\Commands;

use App\Services\OfferLetterExpiryService;
use Illuminate\Console\Command;

class ExpireOfferLetters extends Command
{
    protected $signature = 'agreements:expire-offers';

    protected $description = 'Mark unsigned offer letters past their validity date as expired';

    public function handle(OfferLetterExpiryService $service): int
    {
        $expired = $service->expireStaleOffers();
        $this->info('Expired '.$expired->count().' offer letter(s).');

        return self::SUCCESS;
    }
}
