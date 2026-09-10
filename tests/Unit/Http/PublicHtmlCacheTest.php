<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Http;

use FlatFileCms\Http\PublicHtmlCache;
use FlatFileCms\Http\Request;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use FlatFileCms\Tests\Support\TemporaryProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PublicHtmlCache::class)]
final class PublicHtmlCacheTest extends TestCase
{
    private TemporaryProject $project;
    private SafePathResolver $paths;

    protected function setUp(): void
    {
        $this->project = TemporaryProject::create();
        $this->paths = new SafePathResolver($this->project->path());
    }

    protected function tearDown(): void
    {
        $this->project->remove();
    }

    public function testSeparatesLocalesRoutesQueriesAndReleases(): void
    {
        $cache = new PublicHtmlCache($this->paths, true, 'release-a');
        $request = new Request('GET', '/pl/oferta', query: ['page' => '2', 'tag' => 'web']);
        $cache->put($request, 'pl', 'oferta', $request->query(), '<p>PL</p>', 123);

        $entry = $cache->get($request, 'pl', 'oferta', ['tag' => 'web', 'page' => '2']);
        self::assertNotNull($entry);
        self::assertSame('<p>PL</p>', $entry->html);
        self::assertSame(hash('sha256', '<p>PL</p>'), $entry->contentHash);
        $files = glob($this->project->path('storage/cache/html/*.cache'));
        self::assertIsArray($files);
        self::assertCount(1, $files);
        self::assertIsArray(unserialize((string) file_get_contents($files[0]), ['allowed_classes' => false]));
        self::assertNull($cache->get($request, 'en', 'oferta', $request->query()));
        self::assertNull($cache->get($request, 'pl', 'kontakt', $request->query()));
        self::assertNull((new PublicHtmlCache($this->paths, true, 'release-b'))
            ->get($request, 'pl', 'oferta', $request->query()));
    }

    public function testInvalidationMakesExistingEntriesUnavailable(): void
    {
        $cache = new PublicHtmlCache($this->paths, true, 'release-a');
        $request = new Request('GET', '/pl/');
        $cache->put($request, 'pl', '', [], '<p>Before</p>', 123);
        self::assertNotNull($cache->get($request, 'pl', '', []));

        $cache->invalidate();

        self::assertNull($cache->get($request, 'pl', '', []));
    }

    public function testMissingGenerationMarkerCannotResurrectAnOlderEntry(): void
    {
        $cache = new PublicHtmlCache($this->paths, true, 'release-a');
        $request = new Request('GET', '/pl/');
        $cache->put($request, 'pl', '', [], '<p>Old</p>', 123);
        unlink($this->project->path('storage/cache/html-generation'));

        self::assertNull($cache->get($request, 'pl', '', []));
    }

    public function testDisabledAndPostRequestsAreNeverCached(): void
    {
        $disabled = new PublicHtmlCache($this->paths, false, 'release-a');
        $get = new Request('GET', '/pl/');
        $disabled->put($get, 'pl', '', [], 'disabled', 123);
        self::assertNull($disabled->get($get, 'pl', '', []));

        $cache = new PublicHtmlCache($this->paths, true, 'release-a');
        $request = new Request('POST', '/pl/');
        $cache->put($request, 'pl', '', [], 'private', 123);
        self::assertNull($cache->get($request, 'pl', '', []));
    }

    public function testPublicPageWithAdministratorSessionCookieIsCached(): void
    {
        $cache = new PublicHtmlCache($this->paths, true, 'release-a');
        $request = new Request('GET', '/pl/', headers: ['cookie' => 'cms_session=secret']);
        $cache->put($request, 'pl', '', [], 'public', 123);

        self::assertSame('public', $cache->get($request, 'pl', '', [])?->html);
    }

    public function testRejectsEntryWhoseStoredHashDoesNotMatchHtml(): void
    {
        $cache = new PublicHtmlCache($this->paths, true, 'release-a');
        $request = new Request('GET', '/pl/');
        $cache->put($request, 'pl', '', [], 'original', 123);
        $files = glob($this->project->path('storage/cache/html/*.cache'));
        self::assertIsArray($files);
        file_put_contents($files[0], serialize([
            'html' => 'modified',
            'modifiedAt' => 123,
            'contentHash' => hash('sha256', 'original'),
        ]));

        self::assertNull($cache->get($request, 'pl', '', []));
    }
}
