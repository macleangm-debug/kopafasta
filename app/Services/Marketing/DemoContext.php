<?php

namespace App\Services\Marketing;

use App\Models\MarketingDemoSession;

class DemoContext
{
    private ?MarketingDemoSession $session = null;

    public function activate(MarketingDemoSession $session): void
    {
        $this->session = $session;
    }

    public function current(): ?MarketingDemoSession
    {
        return $this->session;
    }

    public function isActive(): bool
    {
        return $this->session !== null && $this->session->isLive();
    }

    public function clear(): void
    {
        $this->session = null;
    }
}
