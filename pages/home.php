<?php
declare(strict_types=1);

articles_listing_page([
    'heading' => null,                       // homepage bez nadpisu, jen výpis
    'base'    => '/',
    'offset'  => $offset,
    'where'   => 'i.iblog = ?',
    'params'  => [MAIN_BLOG],
    'meta'    => [
        'canonical'   => '/',
        'description' => cfg('claim'),
    ],
]);
