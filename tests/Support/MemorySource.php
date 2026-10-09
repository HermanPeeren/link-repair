<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\LinkRepair\Tests\Support;

use Yepr\Plugin\Task\LinkRepair\Content\ContentItem;
use Yepr\Plugin\Task\LinkRepair\Content\ContentSource;

/**
 * Items of one kind in an array; saves replace them and are recorded.
 */
final class MemorySource implements ContentSource
{
    /** @var array<int, ContentItem> */
    private array $items = [];

    /** @var list<array{id: int, userId: int, note: string}> */
    public array $saves = [];

    public ?string $failSaveWith = null;

    /**
     * @param list<string> $fieldNames
     */
    public function __construct(private readonly string $kind, private readonly array $fieldNames)
    {
    }

    public static function articles(): self
    {
        return new self(ContentItem::ARTICLE, ['introtext', 'fulltext']);
    }

    public static function categories(): self
    {
        return new self(ContentItem::CATEGORY, ['description']);
    }

    public static function modules(): self
    {
        return new self(ContentItem::MODULE, ['content']);
    }

    /**
     * Puts an item with its fields in order; missing fields are empty.
     */
    public function put(int $id, string $first, string $second = '', int $checkedOut = 0, string $title = ''): void
    {
        $values = [$first, $second];
        $fields = [];

        foreach ($this->fieldNames as $index => $name) {
            $fields[$name] = $values[$index] ?? '';
        }

        $this->items[$id] = new ContentItem($this->kind, $id, $title === '' ? ucfirst($this->kind) . ' ' . $id : $title, $fields, $checkedOut);
        ksort($this->items);
    }

    public function get(int $id): ContentItem
    {
        return $this->items[$id];
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function page(int $afterId, int $limit): array
    {
        $page = array_values(array_filter($this->items, static fn (ContentItem $item): bool => $item->id > $afterId));

        return \array_slice($page, 0, $limit);
    }

    public function load(int $id): ?ContentItem
    {
        return $this->items[$id] ?? null;
    }

    public function save(ContentItem $item, array $fields, int $userId, string $note): void
    {
        if ($this->failSaveWith !== null) {
            throw new \RuntimeException($this->failSaveWith);
        }

        $this->items[$item->id] = new ContentItem($item->kind, $item->id, $item->title, array_merge($item->fields, $fields), $item->checkedOut);
        $this->saves[]          = ['id' => $item->id, 'userId' => $userId, 'note' => $note];
    }
}
