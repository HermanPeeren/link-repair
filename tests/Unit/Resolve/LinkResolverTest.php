<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\LinkRepair\Tests\Unit\Resolve;

use PHPUnit\Framework\TestCase;
use Yepr\Plugin\Task\LinkRepair\Resolve\LinkResolver;
use Yepr\Plugin\Task\LinkRepair\Resolve\LinkState;
use Yepr\Plugin\Task\LinkRepair\Resolve\MenuItem;
use Yepr\Plugin\Task\LinkRepair\Resolve\RedirectResult;
use Yepr\Plugin\Task\LinkRepair\Resolve\Resolution;
use Yepr\Plugin\Task\LinkRepair\Tests\Support\FakeMenuIndex;
use Yepr\Plugin\Task\LinkRepair\Tests\Support\FakeRedirectFollower;
use Yepr\Plugin\Task\LinkRepair\Url\InternalLinkFilter;
use Yepr\Plugin\Task\LinkRepair\Url\SiteAddress;

final class LinkResolverTest extends TestCase
{
    private FakeMenuIndex $menus;

    private FakeRedirectFollower $redirects;

    private SiteAddress $site;

    protected function setUp(): void
    {
        $this->menus     = new FakeMenuIndex();
        $this->redirects = new FakeRedirectFollower();
        $this->site      = SiteAddress::fromUrl('https://guide.joomla.org');
    }

    private function resolve(string $href, bool $follow = true, ?LinkResolver $resolver = null): Resolution
    {
        $filter = new InternalLinkFilter();
        $resolver ??= new LinkResolver($this->menus, $follow ? $this->redirects : null, $filter, $this->site);
        $link = $filter->classify($href, $this->site);

        self::assertNotNull($link);

        return $resolver->resolve($link);
    }

    public function testAPathOfAMenuItemIsRepairableWithoutAskingTheSite(): void
    {
        $this->menus->add(12, 'user-guide/seo', 'SEO');

        $resolution = $this->resolve('/user-guide/seo#part');

        self::assertSame(LinkState::REPAIRABLE, $resolution->state);
        self::assertSame(12, $resolution->menuItem?->id);
        self::assertSame('index.php?Itemid=12#part', $resolution->newHref);
        self::assertSame([], $this->redirects->asked);
    }

    public function testAnOldPathIsFollowedToItsMenuItem(): void
    {
        $this->menus->add(12, 'user-guide/seo');
        $this->redirects->redirects('https://guide.joomla.org/user-manual/seo', 'https://guide.joomla.org/user-guide/seo');

        $resolution = $this->resolve('/user-manual/seo');

        self::assertSame(LinkState::REPAIRABLE, $resolution->state);
        self::assertSame('index.php?Itemid=12', $resolution->newHref);
        self::assertSame('https://guide.joomla.org/user-guide/seo', $resolution->finalUrl);
        self::assertSame('via 1 redirect(s)', $resolution->message);
    }

    public function testALinkThatEndsInAnErrorIsBroken(): void
    {
        $resolution = $this->resolve('/jdocmanual');

        self::assertSame(LinkState::BROKEN, $resolution->state);
        self::assertSame('HTTP 404', $resolution->message);
    }

    public function testNoAnswerIsBrokenWithTheReason(): void
    {
        $this->redirects->answers('https://guide.joomla.org/away', new RedirectResult(0, 'https://elsewhere.org/', 1, 'redirects to another site'));

        $resolution = $this->resolve('/away');

        self::assertSame(LinkState::BROKEN, $resolution->state);
        self::assertSame('redirects to another site', $resolution->message);
    }

    public function testAPageThatIsNoMenuItemIsUnmatched(): void
    {
        $url = 'https://guide.joomla.org/blog/some-article';
        $this->redirects->answers($url, new RedirectResult(200, $url, 0));

        self::assertSame(LinkState::UNMATCHED, $this->resolve('/blog/some-article')->state);
    }

    public function testWithoutFollowingRedirectsAnUnknownPathIsUnmatched(): void
    {
        $resolution = $this->resolve('/user-manual/seo', follow: false);

        self::assertSame(LinkState::UNMATCHED, $resolution->state);
        self::assertSame([], $this->redirects->asked);
    }

    public function testALinkWithAQueryIsLeftForAPerson(): void
    {
        $this->menus->add(12, 'user-guide/seo');

        self::assertSame(LinkState::QUERY, $this->resolve('/user-guide/seo?start=10')->state);
    }

    public function testALanguagePrefixChoosesTheMenuItemOfThatLanguage(): void
    {
        $this->menus->addPrefix('fr', 'fr-FR');
        $this->menus->add(3, 'contact', 'Contact', 'en-GB');
        $this->menus->add(4, 'contact', 'Contact', 'fr-FR');

        $resolution = $this->resolve('/fr/contact');

        self::assertSame(4, $resolution->menuItem?->id);
        self::assertSame('index.php?Itemid=4&lang=fr-FR', $resolution->newHref);
    }

    public function testTheRootIsTheHomeMenuItem(): void
    {
        $this->menus->setHome(new MenuItem(101, 'Home', 'home', '*'));

        self::assertSame(101, $this->resolve('/')->menuItem?->id);
    }

    public function testTheSiteIsAskedOncePerLink(): void
    {
        $resolver = new LinkResolver($this->menus, $this->redirects, new InternalLinkFilter(), $this->site);

        $this->resolve('/gone', resolver: $resolver);
        $this->resolve('https://guide.joomla.org/gone', resolver: $resolver);

        self::assertCount(1, $this->redirects->asked);
    }
}
