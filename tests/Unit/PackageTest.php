<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\LinkRepair\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The manifest describes the package, and the package contains what it says.
 *
 * Both directions: everything the manifest lists exists, and every folder of
 * the plugin is listed. A folder that is there but not listed works on a
 * development site and is missing from every real install.
 */
final class PackageTest extends TestCase
{
    private const PLUGIN = '/src/plugins/task/linkrepair';

    private function root(): string
    {
        return \dirname(__DIR__, 2);
    }

    private function manifest(): \SimpleXMLElement
    {
        $xml = simplexml_load_file($this->root() . '/src/linkrepair.xml');

        self::assertNotFalse($xml, 'The manifest is not valid XML.');

        return $xml;
    }

    public function testTheFilesFolderIsWhereThePluginIs(): void
    {
        self::assertSame(
            'plugins/task/linkrepair',
            (string) $this->manifest()->files['folder']
        );
    }

    public function testEveryFolderTheManifestClaimsIsThere(): void
    {
        $missing = [];

        foreach ($this->manifest()->files->folder as $folder) {
            if (!is_dir($this->root() . self::PLUGIN . '/' . $folder)) {
                $missing[] = (string) $folder;
            }
        }

        self::assertSame([], $missing);
    }

    public function testEveryFolderOfThePluginIsListed(): void
    {
        $listed = [];

        foreach ($this->manifest()->files->folder as $folder) {
            $listed[] = (string) $folder;
        }

        $unlisted = array_values(array_diff(
            array_filter(
                scandir($this->root() . self::PLUGIN) ?: [],
                fn (string $entry): bool => $entry[0] !== '.' && is_dir($this->root() . self::PLUGIN . '/' . $entry)
            ),
            $listed
        ));

        self::assertSame([], $unlisted);
    }

    public function testTheSqlFilesAndScriptAreThere(): void
    {
        $manifest = $this->manifest();

        foreach ([$manifest->install->sql->file, $manifest->uninstall->sql->file] as $file) {
            self::assertFileExists($this->root() . self::PLUGIN . '/' . $file);
        }

        self::assertFileExists($this->root() . self::PLUGIN . '/' . $manifest->update->schemas->schemapath . '/' . $manifest->version . '.sql');
        self::assertFileExists($this->root() . '/src/' . $manifest->scriptfile);
    }

    public function testTheTaskFormsAndLanguageFilesAreThere(): void
    {
        self::assertFileExists($this->root() . self::PLUGIN . '/forms/scan.xml');
        self::assertFileExists($this->root() . self::PLUGIN . '/forms/repair.xml');
        self::assertFileExists($this->root() . self::PLUGIN . '/language/en-GB/plg_task_linkrepair.ini');
        self::assertFileExists($this->root() . self::PLUGIN . '/language/en-GB/plg_task_linkrepair.sys.ini');
    }

    public function testEveryLanguageKeyTheFormsAndThePluginUseIsTranslated(): void
    {
        $ini  = (string) file_get_contents($this->root() . self::PLUGIN . '/language/en-GB/plg_task_linkrepair.ini');
        $form = (string) file_get_contents($this->root() . self::PLUGIN . '/forms/scan.xml')
            . (string) file_get_contents($this->root() . self::PLUGIN . '/forms/repair.xml')
            . (string) file_get_contents($this->root() . self::PLUGIN . '/src/Extension/LinkRepair.php');
        $missing = [];

        preg_match_all('/PLG_TASK_LINKREPAIR_[A-Z_]+/', $form, $keys);

        foreach (array_unique($keys[0]) as $key) {
            // A task type's prefix is used with _TITLE and _DESC.
            $translated = preg_match('/^' . $key . '=/m', $ini)
                || (preg_match('/^' . $key . '_TITLE=/m', $ini) && preg_match('/^' . $key . '_DESC=/m', $ini));

            if (!$translated) {
                $missing[] = $key;
            }
        }

        self::assertSame([], $missing);
    }
}
