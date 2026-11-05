<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class CommercialOpenWaIsolationTest extends TestCase
{
    public function test_commercial_openwa_config_does_not_fallback_to_yape_openwa(): void
    {
        $config = file_get_contents(__DIR__ . '/../../Modules/Customer/Config/config.php');

        $this->assertStringContainsString('COMMERCIAL_OPENWA_ENABLED', $config);
        $this->assertStringContainsString('COMMERCIAL_OPENWA_SESSION', $config);
        $this->assertStringNotContainsString('YAPE_OPENWA_', $config);
    }

    public function test_env_example_documents_separate_commercial_session(): void
    {
        $envExample = file_get_contents(__DIR__ . '/../../.env.example');

        $this->assertStringContainsString('COMMERCIAL_OPENWA_ENABLED=false', $envExample);
        $this->assertStringContainsString('COMMERCIAL_OPENWA_SESSION=', $envExample);
        $this->assertStringContainsString('separate WhatsApp number/session from Yape', $envExample);
    }
}
