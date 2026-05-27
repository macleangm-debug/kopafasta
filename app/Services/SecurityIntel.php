<?php

namespace App\Services;

class SecurityIntel
{
    /**
     * Classify an IP address. Returns an array with:
     *   - ip: string|null
     *   - private: bool (RFC1918, loopback, link-local, CGNAT, ULA, etc.)
     *   - country: string|null (ISO-3166-1 alpha-2, if MaxMind DB available)
     *   - asn: int|null (if MaxMind ASN DB available — auto-detected)
     */
    public function classify(?string $ip): array
    {
        $out = ['ip' => $ip, 'private' => false, 'country' => null, 'asn' => null];

        if (! $ip || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            $out['private'] = true;

            return $out;
        }

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            $out['private'] = true;
        }

        // CGNAT 100.64.0.0/10 is not flagged by FILTER_FLAG_NO_PRIV_RANGE.
        if (! $out['private'] && $this->isCgnat($ip)) {
            $out['private'] = true;
        }

        if (! $out['private']) {
            $this->geoEnrich($ip, $out);
        }

        return $out;
    }

    private function isCgnat(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }
        $long = ip2long($ip);
        if ($long === false) {
            return false;
        }
        $start = ip2long('100.64.0.0');
        $end = ip2long('100.127.255.255');

        return $long >= $start && $long <= $end;
    }

    private function geoEnrich(string $ip, array &$out): void
    {
        $dbPath = config('security.geoip_db_path');
        $asnPath = config('security.geoip_asn_db_path');

        if ($dbPath && is_readable($dbPath) && class_exists('MaxMind\\Db\\Reader')) {
            try {
                $reader = new \MaxMind\Db\Reader($dbPath);
                $record = $reader->get($ip);
                $out['country'] = $record['country']['iso_code'] ?? ($record['registered_country']['iso_code'] ?? null);
                $reader->close();
            } catch (\Throwable $t) {
                // ignore lookup errors
            }
        }

        if ($asnPath && is_readable($asnPath) && class_exists('MaxMind\\Db\\Reader')) {
            try {
                $reader = new \MaxMind\Db\Reader($asnPath);
                $record = $reader->get($ip);
                $out['asn'] = isset($record['autonomous_system_number']) ? (int) $record['autonomous_system_number'] : null;
                $reader->close();
            } catch (\Throwable $t) {
                // ignore lookup errors
            }
        }
    }
}
