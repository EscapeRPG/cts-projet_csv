<?php

namespace App\Tests\Service\Import;

use App\Service\Import\SftpClient;
use PHPUnit\Framework\TestCase;

final class SftpClientTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cts-sftp-' . bin2hex(random_bytes(8));
        mkdir($this->root . '/sgs/incoming', 0777, true);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->root)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->root);
    }

    public function testArchivesOnePhysicalVersionAndConsumesExactDuplicate(): void
    {
        $filename = '[S001]_01_01_2026[CONTROLES].csv';
        $incoming = $this->root . '/sgs/incoming/' . $filename;
        file_put_contents($incoming, "id;value\n1;A\n");
        $hash = hash_file('sha256', $incoming);
        $client = new SftpClient('local', $this->root);

        $relative = $client->archiveProcessedVersion('sgs', $filename, $hash);

        self::assertNotNull($relative);
        self::assertFileDoesNotExist($incoming);
        self::assertSame(realpath($this->root . '/' . $relative), $client->resolveArchivedPath($relative));

        file_put_contents($incoming, "id;value\n1;A\n");
        self::assertSame($relative, $client->archiveProcessedVersion('sgs', $filename, $hash));
        self::assertFileDoesNotExist($incoming);
        $archivedFiles = iterator_to_array(new \FilesystemIterator(dirname($this->root . '/' . $relative)));
        self::assertCount(1, $archivedFiles);
    }

    public function testDifferentHashesUseDifferentVersionDirectories(): void
    {
        $filename = '[S001]_01_01_2026[CONTROLES].csv';
        $incoming = $this->root . '/sgs/incoming/' . $filename;
        $client = new SftpClient('local', $this->root);

        file_put_contents($incoming, "id;value\n1;A\n");
        $first = $client->archiveProcessedVersion('sgs', $filename, hash_file('sha256', $incoming));
        file_put_contents($incoming, "id;value\n1;B\n");
        $second = $client->archiveProcessedVersion('sgs', $filename, hash_file('sha256', $incoming));

        self::assertNotSame($first, $second);
        self::assertFileExists($this->root . '/' . $first);
        self::assertFileExists($this->root . '/' . $second);
    }

    public function testFindsLegacyFileThroughHistoricalNetworkFolderAlias(): void
    {
        $filename = '[AS001]_01_01_2026[CONTROLES].csv';
        $legacyDirectory = $this->root . '/autosur/processed';
        mkdir($legacyDirectory, 0777, true);
        file_put_contents($legacyDirectory . '/' . $filename, "id;value\n1;A\n");
        $hash = hash_file('sha256', $legacyDirectory . '/' . $filename);

        $client = new SftpClient('local', $this->root);

        self::assertSame(
            realpath($legacyDirectory . '/' . $filename),
            $client->findLegacyProcessedPath($filename, $hash)
        );
    }
}
