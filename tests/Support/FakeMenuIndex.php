<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\LinkRepair\Tests\Support;

use Yepr\Plugin\Task\LinkRepair\Resolve\MenuIndex;
use Yepr\Plugin\Task\LinkRepair\Resolve\MenuItem;

/**
 * Menu items given by the test.
 */
final class FakeMenuIndex implements MenuIndex
{
    /** @var list<MenuItem> */
    private array $items = [];

    /** @var array<string, string> */
    private array $prefixes = [];

    /** @var array<string, array<int, string>> */
    private array $itemPaths = [];

    private ?MenuItem $home = null;

    public function add(int $id, string $path, string $title = '', string $language = '*'): MenuItem
    {
        $item          = new MenuItem($id, $title === '' ? $path : $title, $path, $language);
        $this->items[] = $item;

        return $item;
    }

    public function setHome(MenuItem $item): void
    {
        $this->home = $item;
    }

    public function addPrefix(string $prefix, string $language): void
    {
        $this->prefixes[$prefix] = $language;
    }

    public function shows(string $kind, int $id, string $path): void
    {
        $this->itemPaths[$kind][$id] = $path;
    }

    public function find(string $path, ?string $language = null): ?MenuItem
    {
        $found = array_values(array_filter($this->items, static fn (MenuItem $item): bool => $item->path === $path));

        foreach ([$language, '*'] as $wanted) {
            foreach ($found as $item) {
                if ($item->language === $wanted) {
                    return $item;
                }
            }
        }

        return $found[0] ?? null;
    }

    public function home(?string $language = null): ?MenuItem
    {
        return $this->home;
    }

    public function languageForPrefix(string $prefix): ?string
    {
        return $this->prefixes[$prefix] ?? null;
    }

    public function pathForItem(string $kind, int $id): ?string
    {
        return $this->itemPaths[$kind][$id] ?? null;
    }
}
