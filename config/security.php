<?php

return [
    'geoip_db_path' => env('GEOIP_DB_PATH'),
    'geoip_asn_db_path' => env('GEOIP_ASN_DB_PATH'),
    'deny_cidrs' => env('SECURITY_DENY_CIDRS'),
    'allow_cidrs' => env('SECURITY_ALLOW_CIDRS'),
];
