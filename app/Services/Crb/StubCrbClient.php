<?php

namespace App\Services\Crb;

use App\Contracts\CrbClientInterface;
use App\DataTransferObjects\CrbIdentityResult;
use App\Support\NidaNumber;

class StubCrbClient implements CrbClientInterface
{
    public function verifyConsumerIdentity(
        string $identifierNumber,
        ?string $fullName = null,
        ?string $dateOfBirth = null,
        ?string $mobile = null,
    ): CrbIdentityResult {
        $formatted = NidaNumber::format($identifierNumber);

        if (! $formatted) {
            return CrbIdentityResult::failed('Invalid NIDA number format.');
        }

        if (config('crb_samples.enabled', true)) {
            if ($scenario = $this->scenarioFor($formatted, 'no_hit')) {
                return CrbIdentityResult::failed(
                    'No matching identity record was found at the credit bureau.',
                    'no_hit',
                    ['driver' => 'stub', 'scenario' => 'no_hit'],
                );
            }

            if ($scenario = $this->scenarioFor($formatted, 'multihit')) {
                return new CrbIdentityResult(
                    success: false,
                    status: 'multihit',
                    message: 'Multiple CRB matches found. Confirm the correct person.',
                    candidates: collect($scenario['candidates'])->map(fn (array $c) => [
                        'entity_key' => $c['entity_key'],
                        'name'       => $c['name'],
                        'dob'        => $c['dob'],
                        'gender'     => $c['gender'],
                        'identifier' => $c['identifier'],
                        'score'      => $c['score'],
                    ])->all(),
                    raw: [
                        'driver'            => 'stub',
                        'scenario'          => 'multihit',
                        'search_request_id' => $scenario['search_request_id'],
                    ],
                );
            }

            if ($scenario = $this->scenarioFor($formatted, 'verified')) {
                return $this->verifiedFromScenario($formatted, $scenario);
            }
        }

        return $this->genericVerified($formatted, $fullName, $dateOfBirth);
    }

    public function fetchByEntityKey(
        string $searchRequestId,
        string $entityKey,
        string $identifierNumber,
    ): CrbIdentityResult {
        $formatted = NidaNumber::format($identifierNumber);

        if (! $formatted) {
            return CrbIdentityResult::failed('Invalid NIDA number format.');
        }

        $scenario = $this->scenarioFor($formatted, 'multihit');

        if ($scenario) {
            foreach ($scenario['candidates'] as $candidate) {
                if (($candidate['entity_key'] ?? '') === $entityKey) {
                    return CrbIdentityResult::verified(
                        fullName: $candidate['name'],
                        firstName: $candidate['first_name'] ?? explode(' ', $candidate['name'])[0],
                        lastName: $candidate['last_name'] ?? '',
                        dateOfBirth: $candidate['date_of_birth'] ?? null,
                        gender: strtolower(str_starts_with(strtolower($candidate['gender']), 'f') ? 'female' : 'male'),
                        nationalId: $formatted,
                        searchScore: ($candidate['score'] ?? 0).'%',
                        crbRuid: 'stub-entity-'.$entityKey,
                        raw: ['driver' => 'stub', 'scenario' => 'multihit_confirm', 'entity_key' => $entityKey],
                    );
                }
            }

            return CrbIdentityResult::failed('Selected CRB match could not be retrieved.', 'no_hit');
        }

        return $this->verifyConsumerIdentity($identifierNumber);
    }

    private function scenarioFor(string $formattedNida, string $key): ?array
    {
        $scenario = config("crb_samples.scenarios.{$key}");

        if (! is_array($scenario) || ($scenario['nida'] ?? '') !== $formattedNida) {
            return null;
        }

        return $scenario;
    }

    private function verifiedFromScenario(string $formatted, array $scenario): CrbIdentityResult
    {
        return CrbIdentityResult::verified(
            fullName: $scenario['full_name'],
            firstName: $scenario['first_name'],
            lastName: $scenario['last_name'],
            dateOfBirth: $scenario['date_of_birth'],
            gender: $scenario['gender'],
            nationalId: $formatted,
            searchScore: $scenario['search_score'] ?? '100%',
            crbRuid: $scenario['crb_ruid'] ?? 'stub-'.substr(NidaNumber::digits($formatted), -8),
            raw: ['driver' => 'stub', 'scenario' => 'verified'],
        );
    }

    private function genericVerified(string $formatted, ?string $fullName, ?string $dateOfBirth): CrbIdentityResult
    {
        $name = trim((string) $fullName);
        if ($name === '') {
            $name = 'Stub Verified Citizen';
        }

        $parts = preg_split('/\s+/', $name) ?: [$name];
        $last = count($parts) > 1 ? array_pop($parts) : 'Citizen';
        $first = implode(' ', $parts) ?: 'Stub';

        return CrbIdentityResult::verified(
            fullName: trim($first.' '.$last),
            firstName: $first,
            lastName: $last,
            dateOfBirth: $dateOfBirth,
            gender: 'male',
            nationalId: $formatted,
            searchScore: '100%',
            crbRuid: 'stub-'.substr(NidaNumber::digits($formatted), -8),
            raw: ['driver' => 'stub', 'scenario' => 'generic'],
        );
    }
}
