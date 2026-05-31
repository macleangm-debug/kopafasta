<?php

namespace App\Services\Crb;

use App\Contracts\CrbClientInterface;
use App\DataTransferObjects\CrbIdentityResult;
use App\Models\Setting;
use App\Support\NidaNumber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DnbLiveCrbClient implements CrbClientInterface
{
    public function verifyConsumerIdentity(
        string $identifierNumber,
        ?string $fullName = null,
        ?string $dateOfBirth = null,
        ?string $mobile = null,
    ): CrbIdentityResult {
        $endpoint = $this->endpoint();
        $email = $this->email();
        $password = config('crb.password');

        if (! $endpoint || ! $email || ! $password) {
            return CrbIdentityResult::failed('CRB credentials are not configured.');
        }

        $identifier = NidaNumber::forCrb($identifierNumber) ?? preg_replace('/\D/', '', $identifierNumber);

        $searchXml = $this->buildConsumerSearchXml(
            identifierNumber: $identifier,
            fullName: $fullName,
            dateOfBirth: $this->formatDobForRequest($dateOfBirth),
            mobile: $mobile,
        );

        $searchResponse = $this->call($endpoint, $email, $password, $searchXml);

        if ($searchResponse === null) {
            return CrbIdentityResult::failed('Unable to reach the credit bureau.');
        }

        if ($multihit = $this->parseMultihit($searchResponse)) {
            $best = collect($multihit['candidates'])->sortByDesc('score')->first();

            if ($best && ($best['score'] ?? 0) >= config('crb.auto_select_min_score')) {
                return $this->fetchSingleHit(
                    $endpoint,
                    $email,
                    $password,
                    (string) $multihit['search_request_id'],
                    (string) $best['entity_key'],
                    $identifierNumber,
                );
            }

            return new CrbIdentityResult(
                success: false,
                status: 'multihit',
                message: 'Multiple CRB matches found. Confirm the correct person.',
                candidates: $multihit['candidates'],
                raw: [
                    'search' => $searchResponse,
                    'search_request_id' => $multihit['search_request_id'],
                ],
            );
        }

        if ($this->isNoHit($searchResponse)) {
            return CrbIdentityResult::failed(
                'No matching identity record was found at the credit bureau.',
                'no_hit',
                ['search' => $searchResponse],
            );
        }

        return $this->parseHitResponse($searchResponse, $identifierNumber);
    }

    private function fetchSingleHit(
        string $endpoint,
        string $email,
        string $password,
        string $searchRequestId,
        string $entityKey,
        string $identifierNumber,
    ): CrbIdentityResult {
        $reviewXml = $this->buildConsumerReviewXml($searchRequestId, $entityKey);
        $reviewResponse = $this->call($endpoint, $email, $password, $reviewXml);

        if ($reviewResponse === null) {
            return CrbIdentityResult::failed('Unable to retrieve the CRB identity report.');
        }

        if ($this->isNoHit($reviewResponse)) {
            return CrbIdentityResult::failed('Selected CRB match could not be retrieved.', 'no_hit');
        }

        return $this->parseHitResponse($reviewResponse, $identifierNumber);
    }

    public function fetchByEntityKey(
        string $searchRequestId,
        string $entityKey,
        string $identifierNumber,
    ): CrbIdentityResult {
        $endpoint = $this->endpoint();
        $email = $this->email();
        $password = config('crb.password');

        if (! $endpoint || ! $email || ! $password) {
            return CrbIdentityResult::failed('CRB credentials are not configured.');
        }

        return $this->fetchSingleHit($endpoint, $email, $password, $searchRequestId, $entityKey, $identifierNumber);
    }

    private function call(string $endpoint, string $email, string $password, string $requestXml): ?string
    {
        $envelope = $this->buildSoapEnvelope($email, $password, $requestXml);

        try {
            $response = Http::timeout(config('crb.timeout'))
                ->withHeaders([
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction'   => 'http://tempuri.org/IReportService/GetLiveCIR',
                ])
                ->withBody($envelope, 'text/xml')
                ->post($endpoint);

            if (! $response->successful()) {
                return null;
            }

            return $this->extractResponseXml($response->body());
        } catch (\Throwable) {
            return null;
        }
    }

    private function buildSoapEnvelope(string $email, string $password, string $requestXml): string
    {
        $escaped = htmlspecialchars($requestXml, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return <<<XML
<?xml version="1.0" encoding="utf-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tem="http://tempuri.org/" xmlns:sil="http://schemas.datacontract.org/2004/07/SilverBladeWeb.Services">
    <soapenv:Header/>
    <soapenv:Body>
        <tem:GetLiveCIR>
            <tem:ReqLiveReport>
                <sil:EmailID>{$email}</sil:EmailID>
                <sil:Password>{$password}</sil:Password>
                <sil:RequestXML>{$escaped}</sil:RequestXML>
            </tem:ReqLiveReport>
        </tem:GetLiveCIR>
    </soapenv:Body>
</soapenv:Envelope>
XML;
    }

    private function buildConsumerSearchXml(
        string $identifierNumber,
        ?string $fullName,
        ?string $dateOfBirth,
        ?string $mobile,
    ): string {
        $reportId = config('crb.consumer_report_id');
        $subjectType = config('crb.consumer_subject_type');
        $responseType = config('crb.response_type');
        $purpose = config('crb.purpose_of_inquiry');

        $name = htmlspecialchars(strtoupper(trim((string) $fullName)), ENT_XML1);
        $identifier = htmlspecialchars($identifierNumber, ENT_XML1);
        $mobileXml = htmlspecialchars(trim((string) $mobile), ENT_XML1);
        $dob = htmlspecialchars((string) $dateOfBirth, ENT_XML1);

        $surrogates = '';
        if ($dob !== '') {
            $surrogates = <<<XML
      <SURROGATES>
        <NATIONALITY>TZ</NATIONALITY>
        <DATEOFBIRTH>{$dob}</DATEOFBIRTH>
      </SURROGATES>
XML;
        }

        return <<<XML
<REQUEST REQUEST_ID="1">
  <REQUEST_PARAMETERS>
    <REPORT_PARAMETERS REPORT_ID="{$reportId}" SUBJECT_TYPE="{$subjectType}" RESPONSE_TYPE="{$responseType}" />
    <PURPOSE_OF_INQUIRY CODE="{$purpose}" />
    <APPLICATION CURRENCY="" AMOUNT="" TYPEOFCREDITFACILITY="" REFERENCENUMBER="" />
  </REQUEST_PARAMETERS>
  <SEARCH_PARAMETERS>
    <NAME>{$name}</NAME>
    <IDENTIFIER_NUMBER>{$identifier}</IDENTIFIER_NUMBER>
    <MOBILE>{$mobileXml}</MOBILE>
    {$surrogates}
    <ACCOUNTNUMBER></ACCOUNTNUMBER>
    <CUSTOMERID></CUSTOMERID>
  </SEARCH_PARAMETERS>
</REQUEST>
XML;
    }

    private function buildConsumerReviewXml(string $searchRequestId, string $entityKey): string
    {
        $reportId = config('crb.consumer_report_id');
        $subjectType = config('crb.consumer_subject_type');
        $responseType = config('crb.response_type');

        return <<<XML
<REQUEST REQUEST_ID="1">
  <REQUEST_PARAMETERS>
    <REPORT_PARAMETERS SEARCH_REQUEST_ID="{$searchRequestId}" REPORT_ID="{$reportId}" SUBJECT_TYPE="{$subjectType}" RESPONSE_TYPE="{$responseType}" />
  </REQUEST_PARAMETERS>
  <SEARCH_PARAMETERS>
    <ENTITYKEY>{$entityKey}</ENTITYKEY>
  </SEARCH_PARAMETERS>
</REQUEST>
XML;
    }

    private function extractResponseXml(string $soapBody): ?string
    {
        if (preg_match('/<DATAPACKET[\s\S]*<\/DATAPACKET>/i', $soapBody, $matches)) {
            return $matches[0];
        }

        if (preg_match('/<!\[CDATA\[([\s\S]*?\]\]>)/i', $soapBody, $matches)) {
            return trim(rtrim($matches[1], ']]>'));
        }

        return $soapBody;
    }

    private function parseMultihit(string $xml): ?array
    {
        if (! str_contains($xml, 'SEARCH-RESULT-LIST') && ! str_contains($xml, 'SEARCH-RESULT-ITEM')) {
            return null;
        }

        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        if (! $doc) {
            return null;
        }

        $searchRequestId = (string) ($doc->HEADER->{'REQUEST-PARAMETERS'}->{'REPORT-PARAMETERS'}['SEARCH-REQUEST-ID'] ?? '');
        $searchRequestId = trim($searchRequestId);

        $candidates = [];
        foreach ($doc->BODY->{'SEARCH-RESULT-LIST'}->{'SEARCH-RESULT-ITEM'} ?? [] as $item) {
            $attrs = $item->attributes();
            $candidates[] = [
                'entity_key' => (string) ($attrs['ENTITY-KEY'] ?? ''),
                'name'       => (string) ($attrs['NAME'] ?? ''),
                'dob'        => (string) ($item->DOB ?? ''),
                'gender'     => (string) ($item->GENDER ?? ''),
                'identifier' => (string) ($item->{'IDENTIFIER-NUMBER'} ?? ''),
                'score'      => (int) preg_replace('/\D/', '', (string) ($item->SEARCHSCORE ?? '0')),
            ];
        }

        if ($candidates === []) {
            return null;
        }

        return [
            'search_request_id' => $searchRequestId,
            'candidates'        => $candidates,
        ];
    }

    private function isNoHit(string $xml): bool
    {
        if (! str_contains($xml, '<RUID>')) {
            return false;
        }

        return (bool) preg_match('/<RUID>\s*-1\s*<\/RUID>/i', $xml);
    }

    private function parseHitResponse(string $xml, string $identifierNumber): CrbIdentityResult
    {
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);

        if (! $doc) {
            return CrbIdentityResult::failed('Unexpected CRB response format.', 'parse_error', ['raw' => $xml]);
        }

        $details = $doc->Cons_CommDetails->Cons_CommDetails ?? $doc->SearchDetails->SearchDetails ?? null;

        if (! $details) {
            return CrbIdentityResult::failed('CRB response did not include identity details.', 'parse_error', ['raw' => $xml]);
        }

        $fullName = trim((string) ($details->ENTITY_NAME_EN ?? $details->NAME ?? ''));
        [$first, $last] = $this->splitName($fullName);

        $dob = $this->normalizeDate((string) ($details->DATE_OF_BIRTH ?? $details->DATEOFBIRTH ?? ''));
        $gender = $this->normalizeGender((string) ($details->GENDER ?? ''));
        $searchScore = trim((string) ($doc->SearchDetails->SearchDetails->SEARCH_SCORE ?? ''));
        $ruid = trim((string) ($doc->ReportDetails->ReportDetails->RUID ?? ''));

        $formattedNida = NidaNumber::format($identifierNumber) ?? $identifierNumber;

        return CrbIdentityResult::verified(
            fullName: $fullName,
            firstName: $first,
            lastName: $last,
            dateOfBirth: $dob,
            gender: $gender,
            nationalId: $formattedNida,
            searchScore: $searchScore !== '' ? $searchScore : null,
            crbRuid: $ruid !== '' && $ruid !== '-1' ? $ruid : null,
            raw: ['response' => $xml],
        );
    }

    private function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];

        if (count($parts) <= 1) {
            return [$fullName, ''];
        }

        $last = array_pop($parts);

        return [implode(' ', $parts), $last];
    }

    private function normalizeGender(string $gender): ?string
    {
        $g = Str::lower(trim($gender));

        return match (true) {
            str_starts_with($g, 'm') => 'male',
            str_starts_with($g, 'f') => 'female',
            $g !== ''                 => 'other',
            default                   => null,
        };
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $formats = ['d-M-Y', 'j-M-Y', 'd M Y', 'Y-m-d', 'd/m/Y'];

        foreach ($formats as $format) {
            $dt = \DateTimeImmutable::createFromFormat($format, $value);
            if ($dt instanceof \DateTimeImmutable) {
                return $dt->format('Y-m-d');
            }
        }

        $ts = strtotime($value);

        return $ts ? date('Y-m-d', $ts) : null;
    }

    private function endpoint(): ?string
    {
        $kyc = Setting::group('kyc');

        return $kyc['crb_endpoint'] ?? config('crb.endpoint');
    }

    private function email(): ?string
    {
        $kyc = Setting::group('kyc');

        return $kyc['crb_email'] ?? config('crb.email');
    }

    private function formatDobForRequest(?string $dateOfBirth): ?string
    {
        if (! $dateOfBirth) {
            return null;
        }

        try {
            return \Carbon\CarbonImmutable::parse($dateOfBirth)->format('j-M-Y');
        } catch (\Throwable) {
            return null;
        }
    }
}
