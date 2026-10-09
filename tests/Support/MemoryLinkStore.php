<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\LinkRepair\Tests\Support;

use Yepr\Plugin\Task\LinkRepair\Store\LinkRecord;
use Yepr\Plugin\Task\LinkRepair\Store\LinkStore;

/**
 * Link records in an array, with ids handed out as a database would.
 */
final class MemoryLinkStore implements LinkStore
{
    /** @var array<int, LinkRecord> */
    public array $records = [];

    private int $nextId = 1;

    public function add(array $records): void
    {
        foreach ($records as $record) {
            $id                 = $this->nextId++;
            $this->records[$id] = $this->with($record, ['id' => $id]);
        }
    }

    public function nextItem(int $scanId, string $state, string $afterKind, int $afterId): ?array
    {
        $next = null;

        foreach ($this->records as $record) {
            $key = [$record->itemKind, $record->itemId];

            if ($record->scanId === $scanId && $record->state === $state && $key > [$afterKind, $afterId] && ($next === null || $key < $next)) {
                $next = $key;
            }
        }

        return $next;
    }

    public function forItem(int $scanId, string $kind, int $itemId, string $state): array
    {
        return array_values(array_filter(
            $this->records,
            static fn (LinkRecord $record): bool => $record->scanId === $scanId
                && $record->itemKind === $kind
                && $record->itemId === $itemId
                && $record->state === $state
        ));
    }

    public function mark(int $id, string $state, string $message): void
    {
        $this->records[$id] = $this->with($this->records[$id], ['state' => $state, 'message' => $message]);
    }

    public function all(int $scanId): iterable
    {
        return array_values(array_filter($this->records, static fn (LinkRecord $record): bool => $record->scanId === $scanId));
    }

    public function counts(int $scanId): array
    {
        $counts = [];

        foreach ($this->all($scanId) as $record) {
            $counts[$record->state] = ($counts[$record->state] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @return list<string>
     */
    public function states(): array
    {
        return array_values(array_map(static fn (LinkRecord $record): string => $record->state, $this->records));
    }

    /**
     * @param array<string, int|string> $changes
     */
    private function with(LinkRecord $record, array $changes): LinkRecord
    {
        $values = get_object_vars($record);

        foreach ($changes as $name => $value) {
            $values[$name] = $value;
        }

        return new LinkRecord(...$values);
    }
}
