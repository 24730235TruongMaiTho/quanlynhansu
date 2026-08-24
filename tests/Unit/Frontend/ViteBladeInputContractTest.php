<?php

namespace Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;

final class ViteBladeInputContractTest extends TestCase
{
    public function test_every_backend_blade_javascript_vite_entry_is_registered_in_vite_input(): void
    {
        $bladeEntries = $this->bladeJavascriptEntries();
        $viteInputs = $this->viteInputs();
        $missing = array_values(array_diff($bladeEntries, $viteInputs));

        self::assertNotEmpty($bladeEntries, 'The backend Blade scan must find at least one JavaScript Vite entry.');
        self::assertSame([], $missing, 'Backend Blade @vite JavaScript entries are missing from vite.config.js: '.implode(', ', $missing));
    }

    /** @return list<string> */
    private function bladeJavascriptEntries(): array
    {
        $entries = [];
        $bladeFiles = [];
        $directory = new \RecursiveDirectoryIterator(
            $this->projectPath('resources/views/backend'),
            \FilesystemIterator::SKIP_DOTS,
        );
        $files = new \RecursiveIteratorIterator($directory);

        foreach ($files as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $bladeFiles[] = $file->getPathname();
            }
        }

        foreach ($bladeFiles as $bladeFile) {
            $source = file_get_contents($bladeFile);
            self::assertIsString($source, "Unable to read Blade source [{$bladeFile}].");

            preg_match_all('/@vite\s*\((.*?)\)/s', $source, $calls);
            foreach ($calls[1] as $call) {
                preg_match_all("/'([^']+\\.js)'/", $call, $matches);
                foreach ($matches[1] as $entry) {
                    $entries[] = $entry;
                }
            }
        }

        return array_values(array_unique($entries));
    }

    /** @return list<string> */
    private function viteInputs(): array
    {
        $config = file_get_contents($this->projectPath('vite.config.js'));
        self::assertIsString($config, 'Unable to read vite.config.js.');
        self::assertMatchesRegularExpression('/input\s*:\s*\[(.*?)\]/s', $config);

        preg_match('/input\s*:\s*\[(.*?)\]/s', $config, $inputMatch);
        preg_match_all("/'([^']+\\.js)'/", $inputMatch[1], $matches);

        return array_values(array_unique($matches[1]));
    }

    private function projectPath(string $path): string
    {
        return dirname(__DIR__, 3).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
    }
}
