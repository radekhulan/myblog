<?php
declare(strict_types=1);

/* Samostatný entrypoint pro /robots.txt (IIS rewrite → /robots.php). DB netřeba. */

require __DIR__ . '/cfg.php';
require __DIR__ . '/lib/helpers.php';   // jen cfg(); žádné DB spojení se nezavádí

header('Content-Type: text/plain; charset=utf-8');

if (cfg('is_dev')) {
    echo "User-agent: *\n";
    echo "Disallow: /\n";
    return;
}

echo "User-agent: *\n";
echo "Allow: /\n";
echo "Disallow: /admin/\n";
echo "Disallow: /ajax/\n";
echo "Disallow: /hledani\n";
echo "Disallow: /tmp/\n";
echo "\n";
echo 'Sitemap: ' . cfg('canonical_base') . "/sitemap.xml\n";
