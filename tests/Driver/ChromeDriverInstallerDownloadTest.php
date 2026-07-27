<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Tests\Driver;

use Myerscode\Beacon\Driver\ChromeDriverInstaller;
use Myerscode\Beacon\Driver\HttpClientInterface;
use Myerscode\Beacon\Support\InstallationResult;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ChromeDriverInstallerDownloadTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/beacon_download_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);
    }

    // =========================================================================
    // download() — chrome not detected
    // =========================================================================

    public function testDownloadSkipsWhenChromeNotDetected(): void
    {
        $httpClient = $this->createStub(HttpClientInterface::class);

        $installer = $this->createInstallerWithChromeVersion(null, $httpClient);

        $result = $installer->download($this->tmpDir);

        $this->assertSame(InstallationResult::STATUS_SKIPPED, $result->status);
        $this->assertStringContainsString('not detected', $result->summary);
    }

    // =========================================================================
    // download() — successful install
    // =========================================================================

    public function testDownloadInstallsBinarySuccessfully(): void
    {
        $platform = (new ChromeDriverInstaller())->platform();
        $binaryName = (new ChromeDriverInstaller())->binaryName();

        $versionsJson = $this->buildVersionsJson('120.0.6099.109', $platform);
        $zipContent = $this->buildZipWithBinary($binaryName);

        $httpClient = $this->createStub(HttpClientInterface::class);
        $httpClient->method('fetch')->willReturnCallback(
            function (string $url) use ($versionsJson, $zipContent): string {
                if (str_contains($url, 'known-good-versions')) {
                    return $versionsJson;
                }

                return $zipContent;
            },
        );

        $installer = $this->createInstallerWithChromeVersion('120.0.6099.109', $httpClient);

        $result = $installer->download($this->tmpDir);

        $this->assertSame(InstallationResult::STATUS_SUCCESS, $result->status);
        $this->assertStringContainsString('installed', $result->summary);
        $this->assertFileExists($this->tmpDir . DIRECTORY_SEPARATOR . $binaryName);
    }

    public function testDownloadIncludesDetectedVersionInMessages(): void
    {
        $platform = (new ChromeDriverInstaller())->platform();
        $binaryName = (new ChromeDriverInstaller())->binaryName();

        $versionsJson = $this->buildVersionsJson('120.0.6099.109', $platform);
        $zipContent = $this->buildZipWithBinary($binaryName);

        $httpClient = $this->createStub(HttpClientInterface::class);
        $httpClient->method('fetch')->willReturnCallback(
            function (string $url) use ($versionsJson, $zipContent): string {
                if (str_contains($url, 'known-good-versions')) {
                    return $versionsJson;
                }

                return $zipContent;
            },
        );

        $installer = $this->createInstallerWithChromeVersion('120.0.6099.109', $httpClient);

        $result = $installer->download($this->tmpDir);

        $this->assertNotEmpty($result->messages);
        $this->assertStringContainsString('120.0.6099.109', $result->messages[0]);
    }

    // =========================================================================
    // download() — extraction failure
    // =========================================================================

    public function testDownloadThrowsWhenExtractionProducesNoBinary(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('extraction failed');

        $platform = (new ChromeDriverInstaller())->platform();
        $versionsJson = $this->buildVersionsJson('120.0.6099.109', $platform);
        $emptyZip = $this->buildEmptyZip();

        $httpClient = $this->createStub(HttpClientInterface::class);
        $httpClient->method('fetch')->willReturnCallback(
            function (string $url) use ($versionsJson, $emptyZip): string {
                if (str_contains($url, 'known-good-versions')) {
                    return $versionsJson;
                }

                return $emptyZip;
            },
        );

        $installer = $this->createInstallerWithChromeVersion('120.0.6099.109', $httpClient);

        $installer->download($this->tmpDir);
    }

    // =========================================================================
    // download() — fetch failure
    // =========================================================================

    public function testDownloadThrowsWhenHttpClientFails(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to fetch');

        $httpClient = $this->createStub(HttpClientInterface::class);
        $httpClient->method('fetch')
            ->willThrowException(new RuntimeException('Failed to fetch URL: https://example.com'));

        $installer = $this->createInstallerWithChromeVersion('120.0.6099.109', $httpClient);

        $installer->download($this->tmpDir);
    }

    // =========================================================================
    // download() — version resolution
    // =========================================================================

    public function testDownloadUsesClosestVersionWhenExactMatchMissing(): void
    {
        $platform = (new ChromeDriverInstaller())->platform();
        $binaryName = (new ChromeDriverInstaller())->binaryName();

        // Versions JSON has 120.0.6099.100 but not 120.0.6099.109
        $versionsJson = $this->buildVersionsJson('120.0.6099.100', $platform);
        $zipContent = $this->buildZipWithBinary($binaryName);

        $httpClient = $this->createStub(HttpClientInterface::class);
        $httpClient->method('fetch')->willReturnCallback(
            function (string $url) use ($versionsJson, $zipContent): string {
                if (str_contains($url, 'known-good-versions')) {
                    return $versionsJson;
                }

                return $zipContent;
            },
        );

        $installer = $this->createInstallerWithChromeVersion('120.0.6099.109', $httpClient);

        $result = $installer->download($this->tmpDir);

        $this->assertSame(InstallationResult::STATUS_SUCCESS, $result->status);
        $this->assertStringContainsString('closest', $result->messages[2] ?? '');
    }

    public function testDownloadThrowsWhenNoVersionForPlatform(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No ChromeDriver download found');

        // Build versions JSON for a different platform than what we're running
        $wrongPlatform = 'nonexistent-platform';
        $versionsJson = $this->buildVersionsJson('120.0.6099.109', $wrongPlatform);

        $httpClient = $this->createStub(HttpClientInterface::class);
        $httpClient->method('fetch')->willReturn($versionsJson);

        $installer = $this->createInstallerWithChromeVersion('120.0.6099.109', $httpClient);

        $installer->download($this->tmpDir);
    }

    public function testDownloadThrowsWhenVersionsJsonIsInvalid(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to parse');

        $httpClient = $this->createStub(HttpClientInterface::class);
        $httpClient->method('fetch')->willReturn('{"invalid": true}');

        $installer = $this->createInstallerWithChromeVersion('120.0.6099.109', $httpClient);

        $installer->download($this->tmpDir);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function createInstallerWithChromeVersion(?string $version, HttpClientInterface $httpClient): ChromeDriverInstaller
    {
        $installer = $this->getMockBuilder(ChromeDriverInstaller::class)
            ->setConstructorArgs([$httpClient])
            ->onlyMethods(['getChromeVersion'])
            ->getMock();

        $installer->method('getChromeVersion')->willReturn($version);

        return $installer;
    }

    private function buildVersionsJson(string $version, string $platform): string
    {
        return json_encode([
            'versions' => [
                [
                    'version' => $version,
                    'downloads' => [
                        'chromedriver' => [
                            [
                                'platform' => $platform,
                                'url' => 'https://storage.example.com/chromedriver-' . $platform . '.zip',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    private function buildZipWithBinary(string $binaryName): string
    {
        $tmpZip = sys_get_temp_dir() . '/beacon_test_zip_' . uniqid() . '.zip';

        $zip = new \ZipArchive();
        $zip->open($tmpZip, \ZipArchive::CREATE);
        $zip->addFromString('chromedriver-linux64/' . $binaryName, "#!/bin/sh\necho 'ChromeDriver 120.0.6099.109'\n");
        $zip->close();

        $content = file_get_contents($tmpZip);
        unlink($tmpZip);

        return $content;
    }

    private function buildEmptyZip(): string
    {
        $tmpZip = sys_get_temp_dir() . '/beacon_test_zip_' . uniqid() . '.zip';

        $zip = new \ZipArchive();
        $zip->open($tmpZip, \ZipArchive::CREATE);
        $zip->addFromString('readme.txt', 'no binary here');
        $zip->close();

        $content = file_get_contents($tmpZip);
        unlink($tmpZip);

        return $content;
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }
}
