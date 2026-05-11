<?php

namespace App\Services\AiProviders\Knowledge;

/**
 * ExcelKnowledgeSource
 *
 * Sample knowledge source for loading knowledge from an Excel file.
 *
 * Usage (in AppServiceProvider::boot()):
 *
 *   KnowledgeBase::setSource(
 *       new ExcelKnowledgeSource(storage_path('knowledge/data.xlsx'))
 *   );
 */
class ExcelKnowledgeSource implements KnowledgeSourceInterface
{
    public function __construct(
        private readonly string $filePath
    ) {}

    public function isAvailable(): bool
    {
        // TODO: check the file exists and any required package is installed
        // e.g. return file_exists($this->filePath) && class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class);
        throw new \RuntimeException('ExcelKnowledgeSource::isAvailable() is not implemented.');
    }

    public function fetch(): string
    {
        // TODO: read the Excel file, parse rows, and return a formatted string
        // for the AI to consume as its knowledge base.
        throw new \RuntimeException('ExcelKnowledgeSource::fetch() is not implemented.');
    }
}