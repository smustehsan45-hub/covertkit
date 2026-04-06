<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
require $root . '/src/bootstrap.php';

use App\Processors\PdfToolkitProcessor;
use Dompdf\Dompdf;

$d = new Dompdf();
$d->loadHtml('<p>One</p>');
$d->render();
$p1 = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 't1-' . uniqid('', true) . '.pdf';
file_put_contents($p1, $d->output());

$d2 = new Dompdf();
$d2->loadHtml('<p>Two</p>');
$d2->render();
$p2 = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 't2-' . uniqid('', true) . '.pdf';
file_put_contents($p2, $d2->output());

$out = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'merged-' . uniqid('', true) . '.pdf';
PdfToolkitProcessor::merge([$p1, $p2], $out);
echo filesize($out) > 100 ? "merge OK\n" : "merge FAIL\n";

$zip = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'split-' . uniqid('', true) . '.zip';
PdfToolkitProcessor::splitToZip($p1, $zip, 1, 50);
echo filesize($zip) > 50 ? "split zip OK\n" : "split FAIL\n";

$wm = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wm-' . uniqid('', true) . '.pdf';
PdfToolkitProcessor::addWatermark($p1, $wm, 'TEST', 0.2, 50);
echo filesize($wm) > 50 ? "watermark OK\n" : "watermark FAIL\n";

@unlink($p1);
@unlink($p2);
@unlink($out);
@unlink($zip);
@unlink($wm);
