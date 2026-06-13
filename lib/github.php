<?php
declare(strict_types=1);

/**
 * Načte počty hvězdiček veřejných repozitářů uživatele z GitHub API.
 * Výsledek se cachuje (sdíleně, mimo repo) — API se volá max jednou za GH_STARS_TTL.
 * Vrací mapu [název_repa_lowercase => stars]; při chybě vrátí poslední cache nebo [].
 */

const GH_STARS_TTL = 21600;   // 6 hodin

function github_stars(string $user): array
{
    $user = preg_replace('/[^\w\-]/', '', $user);
    $cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'myblog-gh-' . $user . '.json';

    $cached = is_file($cacheFile) ? json_decode((string) @file_get_contents($cacheFile), true) : null;
    if (is_array($cached) && isset($cached['t']) && (time() - (int) $cached['t']) < GH_STARS_TTL) {
        return $cached['stars'] ?? [];
    }

    $stars = github_fetch_stars($user);
    if ($stars === null) {
        return is_array($cached) ? ($cached['stars'] ?? []) : [];   // fallback na starou cache
    }

    @file_put_contents($cacheFile, json_encode(['t' => time(), 'stars' => $stars]));
    return $stars;
}

/** Vrátí mapu repo→stars z GitHub API, nebo null při chybě. */
function github_fetch_stars(string $user): ?array
{
    $url = 'https://api.github.com/users/' . $user . '/repos?per_page=100&sort=updated';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 4,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_USERAGENT      => 'MyBlog-CV/1.0',
        CURLOPT_HTTPHEADER     => ['Accept: application/vnd.github+json'],
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($body === false || $code !== 200) {
        blog_log('warn', 'GitHub API nedostupné', ['code' => $code]);
        return null;
    }
    $repos = json_decode((string) $body, true);
    if (!is_array($repos)) {
        return null;
    }
    $stars = [];
    foreach ($repos as $r) {
        if (isset($r['name'])) {
            $stars[strtolower($r['name'])] = (int) ($r['stargazers_count'] ?? 0);
        }
    }
    return $stars;
}
