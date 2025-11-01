<?php

declare(strict_types=1);

namespace Axs4allAi\Tests;

use PHPUnit\Framework\TestCase;

final class VersionConsistencyTest extends TestCase
{
    public function testPluginHeaderMatchesVersionFile(): void
    {
        $root = dirname(__DIR__);
        $pluginFile = $root . DIRECTORY_SEPARATOR . 'axs4all-ai.php';
        $versionFile = $root . DIRECTORY_SEPARATOR . 'version.php';

        $pluginContents = file_get_contents($pluginFile);
        self::assertNotFalse($pluginContents, 'Unable to read axs4all-ai.php');

        $matches = [];
        $found = preg_match('/^\\s*\\*\\s*Version:\\s*([0-9\\.]+)/mi', $pluginContents, $matches);
        self::assertSame(1, $found, 'Could not locate version header in axs4all-ai.php');

        $headerVersion = $matches[1];
        $fileVersion = require $versionFile;

        self::assertSame(
            $headerVersion,
            $fileVersion,
            'Plugin header version and version.php mismatch'
        );
    }
}
