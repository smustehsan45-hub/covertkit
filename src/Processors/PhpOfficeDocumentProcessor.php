<?php

declare(strict_types=1);

namespace App\Processors;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use Smalot\PdfParser\Parser;

/**
 * Word ↔ PDF without LibreOffice: PhpWord + Dompdf (PDF out), PdfParser + PhpWord (text → DOCX).
 */
final class PhpOfficeDocumentProcessor
{
    private string $projectRoot;

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 2);
    }

    public static function dependenciesAvailable(): bool
    {
        return class_exists(IOFactory::class)
            && class_exists(Parser::class)
            && class_exists(\Dompdf\Dompdf::class);
    }

    private function requireComposerAndZip(): void
    {
        if (!self::dependenciesAvailable()) {
            throw new \RuntimeException(
                'Server is missing PHP libraries. On the machine that runs this site, open a terminal in the project folder and run: composer install'
            );
        }
        if (!extension_loaded('zip')) {
            throw new \RuntimeException(
                'PHP zip extension is required for Word/PDF. In php.ini enable: extension=zip (then restart Apache or PHP).'
            );
        }
    }

    public function wordToPdf(string $inputPath, string $outputPath): void
    {
        $this->requireComposerAndZip();

        $dompdfBase = $this->projectRoot . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'dompdf' . DIRECTORY_SEPARATOR . 'dompdf';
        if (!is_dir($dompdfBase)) {
            throw new \RuntimeException('Dompdf is not installed. Run composer install in the project root.');
        }

        if (!Settings::setPdfRenderer(Settings::PDF_RENDERER_DOMPDF, $dompdfBase)) {
            throw new \RuntimeException('Could not initialize the PDF engine (Dompdf path invalid).');
        }

        try {
            $phpWord = $this->loadWordDocument($inputPath);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Could not read this Word file. Try a .docx saved from Word or LibreOffice, or re-save the .doc as .docx.'
            );
        }

        if (!$this->hasContent($phpWord)) {
            throw new \RuntimeException(
                'The Word file appears empty or uses unsupported content features. Save it as a simpler .docx and try again.'
            );
        }

        try {
            $writer = IOFactory::createWriter($phpWord, 'PDF');
            $writer->save($outputPath);
        } catch (\Throwable $e) {
            error_log('[PhpOffice wordToPdf] ' . $e->getMessage());
            throw new \RuntimeException(
                'Could not build the PDF. The document may use features Dompdf cannot render; try a simpler file or split it into smaller parts.'
            );
        }

        if (!is_file($outputPath) || filesize($outputPath) === 0) {
            @unlink($outputPath);
            throw new \RuntimeException('PDF file was not created (empty output).');
        }
    }

    public function pdfToDocx(string $inputPath, string $outputPath): void
    {
        $this->requireComposerAndZip();

        $parser = new Parser();
        try {
            $pdf = $parser->parseFile($inputPath);
            $text = $pdf->getText();
        } catch (\Throwable $e) {
            error_log('[PdfParser] ' . $e->getMessage());
            throw new \RuntimeException(
                'Could not read this PDF. It may be encrypted, damaged, or not a standard text PDF.'
            );
        }

        if (!is_string($text)) {
            $text = '';
        }
        $text = $this->sanitizeText($text);
        $text = trim($text);
        if ($text === '') {
            throw new \RuntimeException(
                'No text could be extracted (common for scanned/image PDFs). Use a PDF that has selectable text, or OCR the file first.'
            );
        }

        try {
            $phpWord = new PhpWord();
            $section = $phpWord->addSection();
            $blocks = preg_split('/\R{2,}/', $text) ?: [$text];
            foreach ($blocks as $block) {
                $line = trim(preg_replace('/\s+/', ' ', str_replace(["\r\n", "\r", "\n"], ' ', $block)));
                if ($line !== '') {
                    $section->addText($line);
                }
                $section->addTextBreak();
            }

            $writer = IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save($outputPath);
        } catch (\Throwable $e) {
            error_log('[PhpOffice pdfToDocx] ' . $e->getMessage());
            @unlink($outputPath);
            throw new \RuntimeException('Could not create the Word file. Try a smaller or simpler PDF.');
        }

        if (!is_file($outputPath) || filesize($outputPath) === 0) {
            @unlink($outputPath);
            throw new \RuntimeException('Word file was not created (empty output).');
        }
    }

    private function sanitizeText(string $text): string
    {
        if ($text === '') {
            return '';
        }
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
            if (is_string($converted) && $converted !== '') {
                return $converted;
            }
        }
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }
        return preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', $text) ?? $text;
    }

    private function loadWordDocument(string $inputPath): PhpWord
    {
        $extension = strtolower(pathinfo($inputPath, PATHINFO_EXTENSION));
        $readerName = match ($extension) {
            'docx' => 'Word2007',
            'doc' => 'MsDoc',
            default => throw new \RuntimeException('Unsupported Word file type. Use .docx or .doc.'),
        };

        return IOFactory::load($inputPath, $readerName);
    }

    private function hasContent(PhpWord $phpWord): bool
    {
        foreach ($phpWord->getSections() as $section) {
            if (count($section->getElements()) > 0) {
                return true;
            }
        }
        return false;
    }
}
