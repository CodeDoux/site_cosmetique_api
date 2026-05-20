<?php
return [
    'master_key'   => env('PAYDUNYA_MASTER_KEY'),
    'private_key'  => env('PAYDUNYA_PRIVATE_KEY'),
    'public_key'   => env('PAYDUNYA_PUBLIC_KEY'),
    'token'        => env('PAYDUNYA_TOKEN'),
    'mode'         => env('PAYDUNYA_MODE', 'test'), // test ou live
    'base_url_test' => 'https://app.paydunya.com/sandbox-api/v1',
    'base_url_live' => 'https://app.paydunya.com/api/v1',
];