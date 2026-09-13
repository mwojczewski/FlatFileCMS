<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Redirects;

use FlatFileCms\Content\InvalidContentException;
use FlatFileCms\Http\Request;
use FlatFileCms\Redirects\RedirectController;
use FlatFileCms\Redirects\RedirectManager;
use FlatFileCms\Redirects\RedirectRepository;
use FlatFileCms\Tests\Support\TemporaryProject;
use FlatFileCms\Tests\Support\TestContentFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RedirectRepositoryTest extends TestCase
{
    private TemporaryProject $project;
    private RedirectRepository $repository;
    private RedirectManager $manager;

    protected function setUp(): void
    {
        $this->project = TemporaryProject::create();
        $this->project->write('config/redirects.yml', "schemaVersion: 1\nredirects: []\n");
        $this->repository = new RedirectRepository(TestContentFactory::yaml($this->project));
        $this->manager = new RedirectManager($this->repository);
    }

    protected function tearDown(): void
    {
        $this->project->remove();
    }

    public function testItPersistsAndResolvesAStatusPreservingRedirect(): void
    {
        $document = $this->repository->get();
        $written = $this->manager->create('/old', '/new', 308, true, $document->revision());
        $response = (new RedirectController($this->repository))->resolve(new Request('GET', '/old'));

        self::assertCount(1, $written->rules());
        self::assertNotNull($response);
        self::assertSame(308, $response->status());
        self::assertSame('/new', $response->headers()['Location']);
    }

    public function testItRejectsRedirectCycles(): void
    {
        $first = $this->manager->create('/one', '/two', 301, true, $this->repository->get()->revision());

        $this->expectException(InvalidContentException::class);
        $this->manager->create('/two', '/one', 301, true, $first->revision());
    }

    #[DataProvider('unsafeInternalTargets')]
    public function testItRejectsDotSegmentsInInternalTargets(string $target): void
    {
        $this->expectException(InvalidContentException::class);

        $this->manager->create('/old', $target, 302, true, $this->repository->get()->revision());
    }

    /** @return iterable<string, array{string}> */
    public static function unsafeInternalTargets(): iterable
    {
        yield 'literal parent' => ['/a/../b'];
        yield 'encoded lowercase parent' => ['/a/%2e%2e/b'];
        yield 'encoded uppercase parent' => ['/a/%2E%2E/b'];
        yield 'partially encoded parent' => ['/a/.%2e/b'];
    }

    public function testItDetectsACycleWhenTargetsContainQueries(): void
    {
        $first = $this->manager->create('/one', '/two?from=one', 301, true, $this->repository->get()->revision());

        $this->expectException(InvalidContentException::class);
        $this->manager->create('/two', '/one?from=two', 301, true, $first->revision());
    }
}
