<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\LinkRepair\Tests\Support;

use Yepr\Plugin\Task\LinkRepair\Content\Article;
use Yepr\Plugin\Task\LinkRepair\Content\ArticleGateway;

/**
 * Articles in an array; saves replace them and are recorded.
 */
final class MemoryArticles implements ArticleGateway
{
    /** @var array<int, Article> */
    private array $articles = [];

    /** @var list<array{id: int, userId: int, note: string}> */
    public array $saves = [];

    public ?string $failSaveWith = null;

    public function put(int $id, string $introtext, string $fulltext = '', int $checkedOut = 0, string $title = ''): void
    {
        $this->articles[$id] = new Article($id, 2, $title === '' ? 'Article ' . $id : $title, $introtext, $fulltext, $checkedOut);
        ksort($this->articles);
    }

    public function get(int $id): Article
    {
        return $this->articles[$id];
    }

    public function page(int $afterId, int $limit): array
    {
        $page = array_values(array_filter($this->articles, static fn (Article $article): bool => $article->id > $afterId));

        return \array_slice($page, 0, $limit);
    }

    public function load(int $id): ?Article
    {
        return $this->articles[$id] ?? null;
    }

    public function save(Article $article, string $introtext, string $fulltext, int $userId, string $note): void
    {
        if ($this->failSaveWith !== null) {
            throw new \RuntimeException($this->failSaveWith);
        }

        $this->put($article->id, $introtext, $fulltext, $article->checkedOut, $article->title);
        $this->saves[] = ['id' => $article->id, 'userId' => $userId, 'note' => $note];
    }
}
