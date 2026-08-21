<?php

namespace Tests\Unit\Support;

use App\Support\DisposableMariaDbGuard;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DisposableMariaDbSafetyTest extends TestCase
{
    public function test_main_database_name_is_always_rejected(): void
    {
        $this->expectException(RuntimeException::class);

        DisposableMariaDbGuard::assertSafeDatabaseName('quan_ly_nhan_su');
    }

    public function test_generated_name_is_accepted(): void
    {
        DisposableMariaDbGuard::assertSafeDatabaseName(
            'quan_ly_nhan_su_employee_test_a1b2c3d4'
        );

        $this->addToAssertionCount(1);
    }

    public function test_mariadb_phpunit_config_forces_sqlite_sentinels(): void
    {
        $document = new DOMDocument();
        $this->assertTrue($document->load(dirname(__DIR__, 3).'/phpunit.mariadb.xml'));
        $xpath = new DOMXPath($document);

        $sentinels = [
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
            'DB_SOCKET' => '',
            'SESSION_DRIVER' => 'array',
            'CACHE_STORE' => 'array',
            'QUEUE_CONNECTION' => 'sync',
        ];

        foreach ($sentinels as $name => $value) {
            $nodes = $xpath->query(sprintf('//env[@name="%s"]', $name));
            $this->assertCount(1, $nodes, "Missing {$name} sentinel.");
            $this->assertSame($value, $nodes->item(0)->getAttribute('value'));
            $this->assertSame('true', $nodes->item(0)->getAttribute('force'));
        }
    }
}
