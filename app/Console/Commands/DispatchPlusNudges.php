<?php

namespace App\Console\Commands;

use App\Services\Plus\PlusNudgeService;
use Illuminate\Console\Command;

class DispatchPlusNudges extends Command
{
    protected $signature = 'plus:dispatch-nudges';

    protected $description = 'Send milestone and inactivity Plus nudges with cooldown';

    public function handle(PlusNudgeService $nudges): int
    {
        $sent = $nudges->dispatchScheduled();
        $this->info("Sent {$sent} Plus nudge(s).");

        return self::SUCCESS;
    }
}
