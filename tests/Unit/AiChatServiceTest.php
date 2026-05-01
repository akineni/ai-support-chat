<?php

namespace Tests\Unit;

use App\Services\AiChatService;
use App\Services\DocumentExtractorService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AiChatServiceTest extends TestCase
{
    private AiChatService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AiChatService();
    }

    // -------------------------------------------------------
    // Escalation Detection
    // -------------------------------------------------------

    public function test_should_escalate_when_response_contains_escalate_tag(): void
    {
        $this->assertTrue(
            $this->service->shouldEscalate('I cannot help with this. [ESCALATE]')
        );
    }

    public function test_should_not_escalate_when_response_is_normal(): void
    {
        $this->assertFalse(
            $this->service->shouldEscalate('Sure, I can help you with that!')
        );
    }

    // -------------------------------------------------------
    // Response Cleaning
    // -------------------------------------------------------

    public function test_clean_response_removes_escalate_tag(): void
    {
        $raw     = 'Let me connect you with an agent. [ESCALATE]';
        $cleaned = $this->service->cleanResponse($raw);

        $this->assertStringNotContainsString('[ESCALATE]', $cleaned);
        $this->assertStringContainsString('Let me connect you with an agent.', $cleaned);
    }

    public function test_clean_response_trims_whitespace(): void
    {
        $raw     = 'Hello there.  [ESCALATE]';
        $cleaned = $this->service->cleanResponse($raw);

        $this->assertEquals('Hello there.', $cleaned);
    }

    public function test_clean_response_leaves_normal_response_unchanged(): void
    {
        $raw = 'Here is the information you need.';

        $this->assertEquals($raw, $this->service->cleanResponse($raw));
    }
}

class DocumentExtractorServiceTest extends TestCase
{
    private DocumentExtractorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DocumentExtractorService();
    }

    public function test_returns_null_when_extraction_is_disabled(): void
    {
        config(['services.document_extraction.enabled' => false]);

        $file   = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');
        $result = $this->service->extract($file);

        $this->assertNull($result);
    }

    public function test_returns_null_for_unsupported_file_type(): void
    {
        config(['services.document_extraction.enabled' => true]);

        $file   = UploadedFile::fake()->create('archive.zip', 100, 'application/zip');
        $result = $this->service->extract($file);

        $this->assertNull($result);
    }

    public function test_extracts_text_from_txt_file(): void
    {
        config(['services.document_extraction.enabled' => true]);

        $file = UploadedFile::fake()->createWithContent(
            'notes.txt',
            'Hello, this is a test document.'
        );

        $result = $this->service->extract($file);

        $this->assertStringContainsString('Hello, this is a test document.', $result);
    }
}