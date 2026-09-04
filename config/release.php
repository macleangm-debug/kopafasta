<?php

return [
    /*
    | Git hash is the technical authority. RELEASE_VERSION is the business label
    | (v1.0.0) written by deploy scripts, not guessed at runtime.
    */
    'version' => env('RELEASE_VERSION', 'dev'),
    'commit' => env('RELEASE_COMMIT'),
    'deployed_at' => env('RELEASE_DEPLOYED_AT'),
    'file' => storage_path('app/release.json'),

    /*
    | Staging banner is on whenever APP_ENV=staging. Production must never show it.
    */
    'show_banner' => env('APP_SHOW_ENV_BANNER'),
];
