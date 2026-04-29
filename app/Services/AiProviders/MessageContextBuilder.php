<?php

namespace App\Services\AiProviders;

class MessageContextBuilder
{
    // -------------------------------------------------------
    // Public — Entry Point
    // -------------------------------------------------------

    public static function buildTextContent(
        string $newMessage,
        array $fileNames = [],
        array $extractedContent = []
    ): string {
        $text = $newMessage;

        if (!empty($extractedContent)) {
            $text .= self::buildExtractedContentContext($extractedContent);
        } elseif (!empty($fileNames)) {
            $text .= self::buildFileNamesContext($fileNames);
        }

        return $text;
    }

    // -------------------------------------------------------
    // Private — Context Builders
    // -------------------------------------------------------

    private static function buildExtractedContentContext(array $extractedContent): string
    {
        /*
         * DOCUMENT EXTRACTION ENABLED
         * ----------------------------
         * When DOCUMENT_EXTRACTION_ENABLED=true, the text content of supported
         * files (txt, doc, docx, pdf) is extracted server-side and injected
         * here so the AI can read and reason about the document content directly.
         */
        $context = '';

        foreach ($extractedContent as $doc) {
            $context .= "\n\n[Content of attached file '{$doc['file_name']}':\n{$doc['content']}]";
        }

        return $context;
    }

    private static function buildFileNamesContext(array $fileNames): string
    {
        /*
         * DOCUMENT EXTRACTION DISABLED (default)
         * ----------------------------------------
         * When DOCUMENT_EXTRACTION_ENABLED=false, the AI cannot read file content.
         * We inform it of the file name only so it can acknowledge the attachment
         * and guide the customer to a human agent for review.
         * This is the recommended default for sensitive documents such as pension
         * documents, IDs, and certificates.
         */
        $fileList = implode(', ', $fileNames);

        return "\n\n[Customer also attached the following file(s): {$fileList}. "
             . "You cannot read the content of these files. Acknowledge the attachment "
             . "and let the customer know a human agent will be able to review it.]";
    }
}