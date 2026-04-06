<?php

declare(strict_types=1);

namespace App\Processors;

use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * PDF merge, split (ZIP), compress (Ghostscript or FPDI rewrite), watermark — TCPDF + FPDI.
 */
final class PdfToolkitProcessor
{
    public static function dependenciesAvailable(): bool
    {
        return class_exists(Fpdi::class);
    }

    /**
     * @param list<string> $paths absolute paths to PDFs, in merge order
     */
    public static function merge(array $paths, string $outputPath, int $maxTotalPages = 300): void
    {
        if (!self::dependenciesAvailable()) {
            throw new \RuntimeException('PDF engine missing. Run composer install.');
        }
        if (count($paths) < 2) {
            throw new \RuntimeException('Need at least two PDF files to merge.');
        }

        $totalPages = self::countPagesAcrossFiles($paths);
        if ($totalPages > $maxTotalPages) {
            throw new \RuntimeException('Too many pages in total (max ' . $maxTotalPages . '). Split the job or merge fewer files.');
        }

        try {
            $pdf = new Fpdi();
            $pdf->SetCreator('ConvertKit');
            foreach ($paths as $path) {
                $pageCount = $pdf->setSourceFile($path);
                for ($p = 1; $p <= $pageCount; $p++) {
                    $tpl = $pdf->importPage($p);
                    $size = $pdf->getTemplateSize($tpl);
                    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $pdf->useTemplate($tpl);
                }
            }
            $pdf->Output($outputPath, 'F');
        } catch (\Throwable $e) {
            error_log('[PdfToolkit merge] ' . $e->getMessage());
            @unlink($outputPath);
            throw new \RuntimeException(
                'Could not merge PDFs. Some files may use encryption or compression this engine cannot read. Try re-saving them as PDF from a viewer.'
            );
        }
    }

    /**
     * @param list<string> $paths
     */
    public static function countPagesAcrossFiles(array $paths): int
    {
        $sum = 0;
        foreach ($paths as $path) {
            $pdf = new Fpdi();
            $sum += $pdf->setSourceFile($path);
        }
        return $sum;
    }

    /**
     * Split one PDF into parts of $pagesPerFile pages each; write a ZIP at $zipPath.
     *
     * @return int number of part PDFs created
     */
    public static function splitToZip(string $inputPath, string $zipPath, int $pagesPerFile, int $maxTotalPages = 300): int
    {
        if (!self::dependenciesAvailable()) {
            throw new \RuntimeException('PDF engine missing. Run composer install.');
        }
        $pagesPerFile = max(1, min(500, $pagesPerFile));

        $probe = new Fpdi();
        $pageCount = $probe->setSourceFile($inputPath);
        if ($pageCount < 1) {
            throw new \RuntimeException('PDF has no pages.');
        }
        if ($pageCount > $maxTotalPages) {
            throw new \RuntimeException('PDF has too many pages (max ' . $maxTotalPages . ').');
        }

        $ranges = [];
        for ($start = 1; $start <= $pageCount; $start += $pagesPerFile) {
            $ranges[] = range($start, min($start + $pagesPerFile - 1, $pageCount));
        }

        $tempDir = dirname($zipPath);
        $partFiles = [];

        try {
            foreach ($ranges as $i => $pageNums) {
                $pdf = new Fpdi();
                $pdf->SetCreator('ConvertKit');
                $pdf->setSourceFile($inputPath);
                foreach ($pageNums as $pn) {
                    $tpl = $pdf->importPage($pn);
                    $size = $pdf->getTemplateSize($tpl);
                    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $pdf->useTemplate($tpl);
                }
                $partPath = $tempDir . DIRECTORY_SEPARATOR . 'split-' . bin2hex(random_bytes(6)) . '-' . ($i + 1) . '.pdf';
                $pdf->Output($partPath, 'F');
                $partFiles[] = $partPath;
            }

            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Could not create ZIP archive.');
            }
            foreach ($partFiles as $idx => $pf) {
                $zip->addFile($pf, 'part-' . sprintf('%03d', $idx + 1) . '.pdf');
            }
            $zip->close();
        } finally {
            foreach ($partFiles as $pf) {
                @unlink($pf);
            }
        }

        return count($partFiles);
    }

    /**
     * Try Ghostscript; if unavailable, rewrite via FPDI (mild size change possible).
     */
    public static function compress(string $inputPath, string $outputPath, string $level, ?string $gsBinary): void
    {
        if (!self::dependenciesAvailable()) {
            throw new \RuntimeException('PDF engine missing. Run composer install.');
        }

        $gs = self::resolveGhostscript($gsBinary);
        $pdfSetting = match (strtolower($level)) {
            'screen' => '/screen',
            'printer' => '/printer',
            'prepress' => '/prepress',
            default => '/ebook',
        };

        if ($gs !== null) {
            $in = realpath($inputPath);
            if ($in === false) {
                throw new \RuntimeException('Input file not found.');
            }
            $cmd = escapeshellarg($gs)
                . ' -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=' . $pdfSetting
                . ' -dNOPAUSE -dBATCH -dQUIET'
                . ' -sOutputFile=' . escapeshellarg($outputPath)
                . ' ' . escapeshellarg($in);
            $out = [];
            $code = 0;
            @exec($cmd . ' 2>&1', $out, $code);
            if ($code === 0 && is_file($outputPath) && filesize($outputPath) > 0) {
                return;
            }
            error_log('[PdfToolkit gs] ' . implode("\n", $out));
            @unlink($outputPath);
        }

        try {
            self::rewritePdf($inputPath, $outputPath);
        } catch (\Throwable $e) {
            error_log('[PdfToolkit compress fallback] ' . $e->getMessage());
            @unlink($outputPath);
            throw new \RuntimeException(
                'Could not compress this PDF. Install Ghostscript (gs) for best results, or try a different file.'
            );
        }
    }

    public static function rewritePdf(string $inputPath, string $outputPath, int $maxPages = 300): void
    {
        $pdf = new Fpdi();
        $pdf->SetCreator('ConvertKit');
        $n = $pdf->setSourceFile($inputPath);
        if ($n > $maxPages) {
            throw new \RuntimeException('Too many pages.');
        }
        for ($i = 1; $i <= $n; $i++) {
            $tpl = $pdf->importPage($i);
            $size = $pdf->getTemplateSize($tpl);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($tpl);
        }
        $pdf->Output($outputPath, 'F');
    }

    public static function addWatermark(string $inputPath, string $outputPath, string $text, float $opacity, int $maxPages = 300): void
    {
        if (!self::dependenciesAvailable()) {
            throw new \RuntimeException('PDF engine missing. Run composer install.');
        }
        $text = trim($text);
        if ($text === '') {
            throw new \RuntimeException('Enter watermark text.');
        }
        if (strlen($text) > 200) {
            $text = substr($text, 0, 200);
        }
        $opacity = max(0.05, min(0.9, $opacity));

        try {
            $pdf = new Fpdi();
            $pdf->SetCreator('ConvertKit');
            $n = $pdf->setSourceFile($inputPath);
            if ($n > $maxPages) {
                throw new \RuntimeException('PDF has too many pages (max ' . $maxPages . ').');
            }
            for ($i = 1; $i <= $n; $i++) {
                $tpl = $pdf->importPage($i);
                $size = $pdf->getTemplateSize($tpl);
                $w = (float) $size['width'];
                $h = (float) $size['height'];
                $pdf->AddPage($size['orientation'], [$w, $h]);
                $pdf->useTemplate($tpl);

                $pdf->SetAlpha($opacity);
                $pdf->SetTextColor(180, 180, 180);
                $pdf->SetFont('helvetica', 'B', 32);
                $cx = $w / 2;
                $cy = $h / 2;
                $pdf->StartTransform();
                $pdf->Rotate(-35, $cx, $cy);
                $tw = $pdf->GetStringWidth($text);
                $pdf->Text($cx - $tw / 2, $cy, $text);
                $pdf->StopTransform();
                $pdf->SetAlpha(1.0);
            }
            $pdf->Output($outputPath, 'F');
        } catch (\Throwable $e) {
            error_log('[PdfToolkit watermark] ' . $e->getMessage());
            @unlink($outputPath);
            throw new \RuntimeException(
                'Could not watermark this PDF. The file may be encrypted or use unsupported features.'
            );
        }
    }

    private static function resolveGhostscript(?string $configured): ?string
    {
        if (is_string($configured) && $configured !== '' && is_executable($configured)) {
            return $configured;
        }
        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return $configured;
        }

        $names = PHP_OS_FAMILY === 'Windows' ? ['gswin64c.exe', 'gswin32c.exe', 'gswin64c', 'gswin32c', 'gs'] : ['gs'];

        foreach ($names as $name) {
            if (PHP_OS_FAMILY === 'Windows') {
                $out = [];
                @exec('where ' . escapeshellcmd($name) . ' 2>nul', $out);
                if (!empty($out[0]) && is_file($p = trim($out[0]))) {
                    return $p;
                }
            } else {
                $which = trim((string) shell_exec('command -v ' . escapeshellcmd($name) . ' 2>/dev/null'));
                if ($which !== '' && is_executable($which)) {
                    return $which;
                }
            }
        }

        return null;
    }
}
