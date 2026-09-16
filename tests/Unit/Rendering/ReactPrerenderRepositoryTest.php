<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Rendering;

use FlatFileCms\Rendering\PrerenderNotFoundException;
use FlatFileCms\Rendering\ReactPrerenderRepository;
use FlatFileCms\Tests\Support\TemporaryProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ReactPrerenderRepository::class)]
final class ReactPrerenderRepositoryTest extends TestCase
{
    private TemporaryProject $project;

    protected function setUp(): void
    {
        $this->project = TemporaryProject::create();
    }

    protected function tearDown(): void
    {
        $this->project->remove();
    }

    public function testItMapsLocaleAndLocalizedPathToIndexHtml(): void
    {
        $this->project->write(
            'public/app/prerender/en/privacy-policy/index.html',
            '<!doctype html><h1>Privacy</h1>',
        );

        $rendered = (new ReactPrerenderRepository($this->project->path()))
            ->render('en', 'privacy-policy');

        self::assertSame('<!doctype html><h1>Privacy</h1>', $rendered->html());
    }

    public function testItMapsHomepageToLocaleIndexHtml(): void
    {
        $this->project->write('public/app/prerender/pl/index.html', '<h1>Start</h1>');

        $rendered = (new ReactPrerenderRepository($this->project->path()))->render('pl', '');

        self::assertSame('<h1>Start</h1>', $rendered->html());
    }

    public function testItRejectsUnsafePaths(): void
    {
        $this->expectException(RuntimeException::class);
        (new ReactPrerenderRepository($this->project->path()))->render('en', '../secret');
    }

    public function testItReportsMissingPrerender(): void
    {
        $this->expectException(PrerenderNotFoundException::class);
        (new ReactPrerenderRepository($this->project->path()))->render('en', 'missing');
    }
}
