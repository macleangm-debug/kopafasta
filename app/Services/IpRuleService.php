<?php

namespace App\Services;

use App\Models\IpRule;
use Illuminate\Support\Facades\Cache;

class IpRuleService
{
    private const CACHE_KEY = 'sec:ip_rules:v1';
    private const CACHE_TTL_SECONDS = 300;

    /**
     * Returns ['allow' => bool, 'deny' => bool, 'matched' => ['mode'=>..,'cidr'=>..,'reason'=>..]|null].
     */
    public function evaluate(?string $ip): array
    {
        $result = ['allow' => false, 'deny' => false, 'matched' => null];
        if (! $ip) {
            return $result;
        }

        foreach ($this->rules() as $rule) {
            if ($this->ipMatchesCidr($ip, $rule['cidr'])) {
                if ($rule['mode'] === IpRule::MODE_DENY) {
                    return [
                        'allow' => false,
                        'deny' => true,
                        'matched' => $rule,
                    ];
                }
                if ($rule['mode'] === IpRule::MODE_ALLOW && ! $result['allow']) {
                    $result['allow'] = true;
                    $result['matched'] = $rule;
                }
            }
        }

        return $result;
    }

    public function isDenied(?string $ip): bool
    {
        return $this->evaluate($ip)['deny'];
    }

    public function isAllowed(?string $ip): bool
    {
        return $this->evaluate($ip)['allow'];
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<int, array{cidr:string,mode:string,reason:?string}>
     */
    private function rules(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): array {
            return IpRule::query()
                ->orderByRaw("CASE mode WHEN 'deny' THEN 0 ELSE 1 END")
                ->get(['cidr', 'mode', 'reason'])
                ->map(fn (IpRule $r) => [
                    'cidr' => $r->cidr,
                    'mode' => $r->mode,
                    'reason' => $r->reason,
                ])
                ->all();
        });
    }

    public function ipMatchesCidr(string $ip, string $cidr): bool
    {
        if (! str_contains($cidr, '/')) {
            return inet_pton($ip) !== false
                && inet_pton($cidr) !== false
                && inet_pton($ip) === inet_pton($cidr);
        }

        [$subnet, $bits] = explode('/', $cidr, 2);
        $bits = (int) $bits;

        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false) {
            return false;
        }
        if (strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $totalBits = strlen($ipBin) * 8;
        if ($bits < 0 || $bits > $totalBits) {
            return false;
        }
        if ($bits === 0) {
            return true;
        }

        $fullBytes = intdiv($bits, 8);
        $remBits = $bits % 8;

        if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes)) {
            return false;
        }

        if ($remBits === 0) {
            return true;
        }

        $mask = chr((0xFF << (8 - $remBits)) & 0xFF);
        return (ord($ipBin[$fullBytes]) & ord($mask)) === (ord($subnetBin[$fullBytes]) & ord($mask));
    }
}
