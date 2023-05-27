<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/Validator.php';

class LibraryTest extends TestCaseSymconValidation
{
    public function testValidateLibrary(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }

    public function testValidateYeelightDevice(): void
    {
        $this->validateModule(__DIR__ . '/../YeelightDevice');
    }

    public function testValidateYeelightDiscovery(): void
    {
        $this->validateModule(__DIR__ . '/../YeelightDiscovery');
    }
}