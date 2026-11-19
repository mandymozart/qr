<?php
require __DIR__ . '/../../vendor/autoload.php';
use chillerlan\QRCode\{QRCode, QROptions};
$want = 'https://qr.viennastruggle.at/?c=ART123'; $res = [];
foreach (glob(__DIR__ . '/png/*.png') as $f) {
    $id = basename($f, '.png'); $ok = false; $how = '';
    foreach ([false, true] as $inv) {
        try { $r = (new QRCode(new QROptions(['readerInvertColors' => $inv, 'readerGrayscale' => true])))->readFromFile($f);
              if ((string) $r === $want) { $ok = true; $how = $inv ? 'only when inverted' : 'normal'; break; } } catch (Throwable $e) { $how = $e->getMessage(); }
    }
    $res[$id] = $ok && $how === 'normal';
    printf("%-9s %s (%s)\n", $id, $ok ? 'OK' : 'FAIL', $how);
}
file_put_contents(__DIR__ . '/results.json', json_encode($res));
