<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Short link base URL for guarantor invitations
    |--------------------------------------------------------------------------
    |
    | When set (e.g. https://cpf.ly), share links use {base}/g/{code}.
    | When null, falls back to app base URL + /g/{code}.
    |
    */
    'short_link_base' => env('GUARANTOR_SHORT_LINK_BASE', null),

    'short_link_path' => '/g',
];
