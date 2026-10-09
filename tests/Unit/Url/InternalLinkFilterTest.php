<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\LinkRepair\Tests\Unit\Url;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yepr\Plugin\Task\LinkRepair\Url\InternalLinkFilter;
use Yepr\Plugin\Task\LinkRepair\Url\SiteAddress;

final class InternalLinkFilterTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: ?string}>
     */
    public static function links(): array
    {
        return [
            'root-relative path'             => ['/user-manual/seo/seo-basics', 'user-manual/seo/seo-basics'],
            'relative path counts from root' => ['user-guide/seo', 'user-guide/seo'],
            'absolute, own host'             => ['https://guide.joomla.org/tutorials/menus/', 'tutorials/menus'],
            'other host name of the site'    => ['http://www.guide.joomla.org/x', 'x'],
            'protocol-relative'              => ['//guide.joomla.org/y', 'y'],
            'index.php path'                 => ['/index.php/user-manual/seo', 'user-manual/seo'],
            'html suffix'                    => ['/user-manual/seo.html', 'user-manual/seo'],
            'root'                           => ['/', ''],
            'encoded characters'             => ['/caf%C3%A9', 'café'],
            'other site'                     => ['https://docs.joomla.org/Help', null],
            'mail'                           => ['mailto:someone@example.org', null],
            'phone'                          => ['tel:+31123', null],
            'script'                         => ['javascript:void(0)', null],
            'anchor'                         => ['#top', null],
            'empty'                          => ['', null],
            'joomla link'                    => ['index.php?option=com_content&view=article&id=3', null],
            'joomla link, absolute'          => ['/index.php?Itemid=4', null],
            'image'                          => ['/images/logo.png', null],
            'pdf'                            => ['https://guide.joomla.org/files/guide.pdf', null],
        ];
    }

    #[DataProvider('links')]
    public function testClassifies(string $href, ?string $path): void
    {
        $site = SiteAddress::fromUrl('https://guide.joomla.org', ['www.guide.joomla.org']);
        $link = (new InternalLinkFilter())->classify($href, $site);

        self::assertSame($path, $link?->path);
    }

    public function testKeepsQueryAndFragmentApart(): void
    {
        $site = SiteAddress::fromUrl('https://guide.joomla.org');
        $link = (new InternalLinkFilter())->classify('/user-guide/seo?start=10#part', $site);

        self::assertNotNull($link);
        self::assertSame('user-guide/seo', $link->path);
        self::assertSame('start=10', $link->query);
        self::assertSame('part', $link->fragment);
        self::assertSame('/user-guide/seo?start=10#part', $link->href);
    }

    public function testASiteInAFolderOnlyClaimsPathsInThatFolder(): void
    {
        $site   = SiteAddress::fromUrl('http://localhost/site/');
        $filter = new InternalLinkFilter();

        self::assertSame('about/team', $filter->classify('/site/about/team', $site)?->path);
        self::assertSame('about', $filter->classify('http://localhost/site/about', $site)?->path);
        self::assertNull($filter->classify('/other-site/about', $site));
        self::assertNull($filter->classify('/sitemap', $site));
    }

    public function testAPortIsPartOfTheHost(): void
    {
        $site   = SiteAddress::fromUrl('http://localhost:8080');
        $filter = new InternalLinkFilter();

        self::assertSame('a', $filter->classify('http://localhost:8080/a', $site)?->path);
        self::assertNull($filter->classify('http://localhost/a', $site));
    }

    public function testASiteAddressNeedsAHost(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SiteAddress::fromUrl('guide.joomla.org');
    }

    public function testAbsoluteUrlsOfPaths(): void
    {
        self::assertSame('http://localhost/site/a/b', SiteAddress::fromUrl('http://localhost/site/index.php')->absolute('a/b'));
        self::assertSame('https://guide.joomla.org/', SiteAddress::fromUrl('https://guide.joomla.org')->absolute(''));
    }
}
