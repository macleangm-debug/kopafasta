<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\Response;

class HttpCache
{
    public static function preventStore(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
