<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
require $root . '/src/bootstrap.php';

use App\Processors\PhpOfficeDocumentProcessor;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ck-test.docx';
$out = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ck-test.pdf';

$w = new PhpWord();
$w->addSection()->addText('Hello PDF');
IOFactory::createWriter($w, 'Word2007')->save($tmp);

$p = new PhpOfficeDocumentProcessor($root);
$p->wordToPdf($tmp, $out);

echo is_file($out) && filesize($out) > 0 ? "wordToPdf OK (" . filesize($out) . " bytes)\n" : "wordToPdf FAIL\n";
@unlink($tmp);
@unlink($out);

$pdfTmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ck-src.pdf';
$docxOut = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ck-out.docx';
$d = new \Dompdf\Dompdf();
$d->loadHtml('<p>Hello from PDF text layer.</p>');
$d->render();
file_put_contents($pdfTmp, $d->output());
$p->pdfToDocx($pdfTmp, $docxOut);
echo is_file($docxOut) && filesize($docxOut) > 0 ? "pdfToDocx OK (" . filesize($docxOut) . " bytes)\n" : "pdfToDocx FAIL\n";
@unlink($pdfTmp);
@unlink($docxOut);
