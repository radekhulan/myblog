<?php
declare(strict_types=1);

require_once DIR_ROOT . '/lib/gallery.php';

/*
 * Administrace fotogalerie: zpracování nahraných obrázků (Imagick) a CRUD nad
 * fotkami/alby. Struktura souborů kopíruje starý web:
 *   plná fotka     →  media/foto/{oid/1000}/img{hash}.jpg     (nejdelší strana ≤ 1500)
 *   střední náhled →  media/thumb/{oid/1000}/mthu{hash}.jpg   (nejdelší strana ≤ 600)
 *   malý náhled    →  media/thumb/{oid/1000}/thu{hash}.jpg    (čtverec 200×200, ořez na střed)
 * Vše se ukládá jako JPEG (vstup JPEG / PNG / WEBP). Adresář = oid/1000 (foto_dir()).
 */

const GALLERY_FULL_MAX       = 1500;
const GALLERY_MEDIUM_MAX      = 600;
const GALLERY_THUMB_SIZE      = 200;
const GALLERY_Q_FULL          = 85;
const GALLERY_Q_MEDIUM        = 82;
const GALLERY_Q_THUMB         = 82;
const GALLERY_ALLOWED_MIME    = ['image/jpeg', 'image/png', 'image/webp'];
const GALLERY_CAPTION_MAXLEN  = 240;

/** Fyzická základna media souborů (mimo repo — junction / IIS rewrite). */
function gallery_media_base(): string
{
    return cfg('media_dir') . '/media';
}

/** Náhodný 32znakový hex hash pro názvy souborů (img/thu/mthu). */
function gallery_hash(): string
{
    return bin2hex(random_bytes(16));
}

/**
 * Fyzické cesty tří variant fotky podle oid (adresář) a uložených názvů souborů.
 * $onahled = "thu{hash}.jpg", $osoubor = "img{hash}.jpg".
 */
function gallery_file_paths(int $oid, string $onahled, string $osoubor): array
{
    $dir  = (string) foto_dir($oid);
    $base = gallery_media_base();
    return [
        'full'   => $base . '/foto/' . $dir . '/' . $osoubor,
        'medium' => $base . '/thumb/' . $dir . '/m' . $onahled,
        'thumb'  => $base . '/thumb/' . $dir . '/' . $onahled,
    ];
}

/**
 * Vytvoří tři varianty obrázku ze zdrojového souboru. Vrací [šířka, výška, kB]
 * plné fotky. Vyhazuje výjimku při selhání Imagicku.
 */
function gallery_render_variants(string $srcPath, array $paths): array
{
    $img = new Imagick();
    $img->readImage($srcPath);

    // Animovaný WEBP/GIF → ponech jen první snímek.
    if ($img->getNumberImages() > 1) {
        $img->setIteratorIndex(0);
        $first = $img->getImage();
        $img->clear();
        $img = $first;
    }

    $img->autoOrient();                 // srovnání dle EXIF
    // Průhlednost (PNG/WEBP) podlož bílou — jinak by v JPEGu zčernala.
    $img->setImageBackgroundColor(new ImagickPixel('white'));
    $flat = $img->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
    $img->clear();
    $img = $flat;
    $img->setImageFormat('jpeg');

    try {
        // Plná fotka — zmenši jen pokud přesahuje (nenafukovat malé).
        $full = clone $img;
        if ($full->getImageWidth() > GALLERY_FULL_MAX || $full->getImageHeight() > GALLERY_FULL_MAX) {
            $full->thumbnailImage(GALLERY_FULL_MAX, GALLERY_FULL_MAX, true);
        }
        $full->setImageCompressionQuality(GALLERY_Q_FULL);
        $full->stripImage();
        $ow = $full->getImageWidth();
        $oh = $full->getImageHeight();
        $full->writeImage($paths['full']);
        $full->clear();

        // Střední náhled.
        $med = clone $img;
        if ($med->getImageWidth() > GALLERY_MEDIUM_MAX || $med->getImageHeight() > GALLERY_MEDIUM_MAX) {
            $med->thumbnailImage(GALLERY_MEDIUM_MAX, GALLERY_MEDIUM_MAX, true);
        }
        $med->setImageCompressionQuality(GALLERY_Q_MEDIUM);
        $med->stripImage();
        $med->writeImage($paths['medium']);
        $med->clear();

        // Malý čtvercový náhled (ořez na střed).
        $thumb = clone $img;
        $thumb->setImageCompressionQuality(GALLERY_Q_THUMB);
        $thumb->cropThumbnailImage(GALLERY_THUMB_SIZE, GALLERY_THUMB_SIZE);
        $thumb->stripImage();
        $thumb->writeImage($paths['thumb']);
        $thumb->clear();
    } finally {
        $img->clear();
    }

    $okb = (int) round(filesize($paths['full']) / 1024);
    return [$ow, $oh, $okb];
}

/**
 * Zpracuje nahraný soubor: vytvoří varianty, vloží řádek do foto_fotka,
 * přepočítá album. Vrací pole s daty fotky, nebo string s chybovou hláškou.
 *
 * @return array|string
 */
function gallery_add_photo(int $fid, string $tmpPath, string $origName, string $albumName)
{
    $info = @getimagesize($tmpPath);
    if ($info === false || !in_array($info['mime'] ?? '', GALLERY_ALLOWED_MIME, true)) {
        return 'Nepodporovaný formát souboru (povoleno JPEG, PNG, WEBP).';
    }

    $caption = trim((string) pathinfo($origName, PATHINFO_FILENAME));
    if ($caption === '') {
        $caption = $albumName;
    }
    if (mb_strlen($caption) > GALLERY_CAPTION_MAXLEN) {
        $caption = mb_substr($caption, 0, GALLERY_CAPTION_MAXLEN);
    }

    $hash    = gallery_hash();
    $onahled = 'thu' . $hash . '.jpg';
    $osoubor = 'img' . $hash . '.jpg';

    // Nejprve INSERT — oid určuje adresář (oid/1000).
    exec_q(
        'INSERT INTO ' . tbl('foto_fotka')
        . ' (fid, onazev, odatum, onahled, osoubor, otyp, oviews, ohodnoceni, okb, ow, oh)'
        . ' VALUES (?, ?, NOW(), ?, ?, 0, 0, 0, 0, 0, 0)',
        [$fid, $caption, $onahled, $osoubor]
    );
    $oid = (int) db()->insert_id;

    $paths = gallery_file_paths($oid, $onahled, $osoubor);
    foreach ([dirname($paths['full']), dirname($paths['thumb'])] as $d) {
        if (!is_dir($d)) {
            @mkdir($d, 0775, true);
        }
    }

    try {
        [$ow, $oh, $okb] = gallery_render_variants($tmpPath, $paths);
    } catch (\Throwable $e) {
        exec_q('DELETE FROM ' . tbl('foto_fotka') . ' WHERE oid = ?', [$oid]);
        @unlink($paths['full']);
        @unlink($paths['medium']);
        @unlink($paths['thumb']);
        blog_log('error', 'gallery: zpracování obrázku selhalo', ['err' => $e->getMessage(), 'oid' => $oid, 'file' => $origName]);
        return 'Obrázek se nepodařilo zpracovat.';
    }

    exec_q('UPDATE ' . tbl('foto_fotka') . ' SET ow = ?, oh = ?, okb = ? WHERE oid = ?', [$ow, $oh, $okb, $oid]);
    gallery_recount($fid);

    return [
        'oid'       => $oid,
        'caption'   => $caption,
        'thumb_url' => foto_thumb_url($oid, $onahled),
        'full_url'  => foto_full_url($oid, $osoubor),
        'w'         => $ow,
        'h'         => $oh,
    ];
}

/** Smaže jednu fotku (soubory i řádek) a přepočítá album. */
function gallery_delete_photo(int $oid): bool
{
    $f = one('SELECT oid, fid, onahled, osoubor FROM ' . tbl('foto_fotka') . ' WHERE oid = ?', [$oid]);
    if (!$f) {
        return false;
    }
    gallery_unlink_files($oid, (string) $f['onahled'], (string) $f['osoubor']);
    exec_q('DELETE FROM ' . tbl('foto_fotka') . ' WHERE oid = ?', [$oid]);
    gallery_recount((int) $f['fid']);
    return true;
}

/** Smaže celé album: všechny fotky (soubory i řádky) a samotné album. */
function gallery_delete_album(int $fid): void
{
    $photos = all('SELECT oid, onahled, osoubor FROM ' . tbl('foto_fotka') . ' WHERE fid = ?', [$fid]);
    foreach ($photos as $p) {
        gallery_unlink_files((int) $p['oid'], (string) $p['onahled'], (string) $p['osoubor']);
    }
    exec_q('DELETE FROM ' . tbl('foto_fotka') . ' WHERE fid = ?', [$fid]);
    exec_q('DELETE FROM ' . tbl('foto') . ' WHERE fid = ?', [$fid]);
}

/** Smaže tři soubory fotky z disku (tichý, chybějící ignoruje). */
function gallery_unlink_files(int $oid, string $onahled, string $osoubor): void
{
    foreach (gallery_file_paths($oid, $onahled, $osoubor) as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

/** Přepočítá počet fotek, obálku (oid) a datum změny alba. */
function gallery_recount(int $fid): void
{
    $cnt   = (int) scalar('SELECT COUNT(*) FROM ' . tbl('foto_fotka') . ' WHERE fid = ? AND otyp = 0', [$fid]);
    $cover = (int) (scalar(
        'SELECT oid FROM ' . tbl('foto_fotka') . ' WHERE fid = ? AND otyp = 0 ORDER BY ohodnoceni DESC, ' . GALLERY_PHOTO_ORDER . ' LIMIT 1',
        [$fid]
    ) ?? 0);
    exec_q('UPDATE ' . tbl('foto') . ' SET ffotek = ?, oid = ?, fzmena = NOW() WHERE fid = ?', [$cnt, $cover, $fid]);
}

/** Kategorie blogu fotogalerie (FOTO_BLOG) pro selecty v administraci. */
function gallery_foto_categories(): array
{
    return all(
        'SELECT catid, cname, iurltitle FROM ' . tbl('category') . ' WHERE cblog = ? ORDER BY csort, cname',
        [FOTO_BLOG]
    );
}
