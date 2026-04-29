<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\IOFactory;
use Smalot\PdfParser\Parser;

class DocumentExtractorService
{
    // -------------------------------------------------------
    // Public — Entry Point
    // -------------------------------------------------------

    public function extract(UploadedFile $file): ?string
    {
        if (!$this->isEnabled()) {
            return null;
        }

        if (!$this->isSupportedType($file)) {
            return null;
        }

        return $this->attemptExtraction($file);
    }

    // -------------------------------------------------------
    // Private — Guards
    // -------------------------------------------------------

    private function isEnabled(): bool
    {
        return config('services.document_extraction.enabled', false);
    }

    private function isSupportedType(UploadedFile $file): bool
    {
        return in_array(
            strtolower($file->getClientOriginalExtension()),
            $this->supportedExtensions()
        );
    }

    private function supportedExtensions(): array
    {
        return ['txt', 'doc', 'docx', 'pdf'];
    }

    // -------------------------------------------------------
    // Private — Extraction Router
    // -------------------------------------------------------

    private function attemptExtraction(UploadedFile $file): ?string
    {
        try {
            return $this->extractByExtension($file);
        } catch (\Throwable $e) {
            $this->logExtractionFailure($file, $e);

            return null;
        }
    }

    private function extractByExtension(UploadedFile $file): ?string
    {
        return match (strtolower($file->getClientOriginalExtension())) {
            'txt'        => $this->extractTxt($file),
            'doc', 'docx'=> $this->extractWord($file),
            'pdf'        => $this->extractPdf($file),
            default      => null,
        };
    }

    // -------------------------------------------------------
    // Private — Extractors
    // -------------------------------------------------------

    private function extractTxt(UploadedFile $file): string
    {
        return file_get_contents($file->getRealPath());
    }

    private function extractWord(UploadedFile $file): string
    {
        $phpWord  = IOFactory::load($file->getRealPath());
        $sections = $phpWord->getSections();
        $lines    = [];

        foreach ($sections as $section) {
            $lines = array_merge($lines, $this->extractSectionText($section));
        }

        return implode("\n", array_filter($lines));
    }

    private function extractSectionText($section): array
    {
        $lines = [];

        foreach ($section->getElements() as $element) {
            $lines[] = $this->extractElementText($element);
        }

        return array_filter($lines);
    }

    private function extractElementText($element): ?string
    {
        if (method_exists($element, 'getText')) {
            return $element->getText();
        }

        if (method_exists($element, 'getElements')) {
            return $this->extractNestedElementText($element);
        }

        return null;
    }

    private function extractNestedElementText($element): ?string
    {
        $lines = [];

        foreach ($element->getElements() as $child) {
            if (method_exists($child, 'getText')) {
                $lines[] = $child->getText();
            }
        }

        return implode(' ', array_filter($lines)) ?: null;
    }

    private function extractPdf(UploadedFile $file): string
    {
        $parser = new Parser();
        $pdf    = $parser->parseFile($file->getRealPath());

        return $pdf->getText();
    }

    // -------------------------------------------------------
    // Private — Logging
    // -------------------------------------------------------

    private function logExtractionFailure(UploadedFile $file, \Throwable $e): void
    {
        Log::warning('Document text extraction failed', [
            'file'      => $file->getClientOriginalName(),
            'extension' => $file->getClientOriginalExtension(),
            'error'     => $e->getMessage(),
        ]);
    }
}