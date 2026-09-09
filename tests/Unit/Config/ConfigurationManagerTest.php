<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Config;

use FlatFileCms\Config\ConfigurationManager;
use FlatFileCms\Config\ConfigurationRepository;
use FlatFileCms\Config\GlobalConfigurationInput;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use FlatFileCms\Rendering\LayoutRegistry;
use FlatFileCms\Support\ContentData;
use FlatFileCms\Tests\Support\TemporaryProject;
use FlatFileCms\Tests\Support\TestContentFactory;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ConfigurationManagerTest extends TestCase
{
    private TemporaryProject $project;
    private ConfigurationManager $manager;

    protected function setUp(): void
    {
        $this->project = TemporaryProject::create();
        $this->project->write('templates/layouts/default.php', '<?php declare(strict_types=1);');
        $this->project->write('config/setup.yml', <<<'YAML'
schemaVersion: 1
site: { name: Old, url: https://old.example, defaultLayout: default }
seo: {}
media: {}
custom:
  preserved: true
YAML);
        $yaml = TestContentFactory::yaml($this->project);
        $repository = new ConfigurationRepository($yaml, new SafePathResolver($this->project->path()));
        $this->manager = new ConfigurationManager($repository, new LayoutRegistry($this->project->path()));
    }

    protected function tearDown(): void
    {
        $this->project->remove();
    }

    public function testItUpdatesWhitelistedConfigurationAndPreservesCustomSections(): void
    {
        $current = $this->manager->editable();
        $updated = $this->manager->update($this->input(), $current->revision());
        $data = $updated->data();
        $site = ContentData::map($data['site'] ?? null, 'site');
        $seo = ContentData::map($data['seo'] ?? null, 'seo');

        self::assertSame('Example', $site['name']);
        self::assertSame(['preserved' => true], $data['custom']);
        self::assertSame(['pl' => 'Opis', 'en' => 'Description'], $seo['description']);
    }

    public function testItRejectsUnknownLayoutBeforeWriting(): void
    {
        $current = $this->manager->editable();
        $input = $this->input(defaultLayout: 'missing');

        $this->expectException(InvalidArgumentException::class);
        $this->manager->update($input, $current->revision());
    }

    private function input(string $defaultLayout = 'default'): GlobalConfigurationInput
    {
        return new GlobalConfigurationInput(
            'Example',
            'https://example.com',
            $defaultLayout,
            ['pl' => 'Example', 'en' => 'Example'],
            ['pl' => 'Opis', 'en' => 'Description'],
            null,
            [],
            [],
            [],
            10_000_000,
            ['image/jpeg', 'image/png'],
            true,
            true,
            true,
            ['webp'],
            82,
            2048,
            2048,
            20_000_000,
        );
    }
}
