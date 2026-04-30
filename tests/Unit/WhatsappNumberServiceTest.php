<?php

namespace Tests\Unit;

use App\Services\WhatsappNumberService;
use Tests\TestCase;

class WhatsappNumberServiceTest extends TestCase
{
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WhatsappNumberService();
    }

    /** @test */
    public function it_normalizes_number_with_leading_zero()
    {
        $this->assertEquals('6281247947246', $this->service->normalize('081247947246'));
    }

    /** @test */
    public function it_normalizes_number_without_country_code()
    {
        $this->assertEquals('6281247947246', $this->service->normalize('81247947246'));
    }

    /** @test */
    public function it_keeps_number_with_62_prefix()
    {
        $this->assertEquals('6281247947246', $this->service->normalize('6281247947246'));
    }

    /** @test */
    public function it_removes_plus_sign()
    {
        $this->assertEquals('6281247947246', $this->service->normalize('+6281247947246'));
    }

    /** @test */
    public function it_removes_spaces_and_dashes()
    {
        $this->assertEquals('6281247947246', $this->service->normalize('0812-4794-7246'));
        $this->assertEquals('6281247947246', $this->service->normalize('0812 4794 7246'));
    }

    /** @test */
    public function it_removes_parentheses()
    {
        $this->assertEquals('6281247947246', $this->service->normalize('(0812) 4794 7246'));
    }

    /** @test */
    public function it_handles_number_with_mixed_separators()
    {
        $this->assertEquals('6281247947246', $this->service->normalize('+62 (812) 4794-7246'));
    }
}
