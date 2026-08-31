<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Admin;

use FlatFileCms\Admin\AdminView;
use FlatFileCms\Rendering\OutputBuffer;
use FlatFileCms\Tests\Support\TemporaryProject;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AdminViewTest extends TestCase
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

    public function testItRendersNamedViewWithEscapingHelper(): void
    {
        $this->project->write('templates/admin/example/view.php', '<p><?= $escape($value) ?></p>');
        $view = new AdminView($this->project->path(), new OutputBuffer());

        self::assertSame('<p>&lt;unsafe&gt;</p>', $view->render('example/view', ['value' => '<unsafe>']));
    }

    public function testItRejectsPathTraversalInViewName(): void
    {
        $view = new AdminView($this->project->path(), new OutputBuffer());

        $this->expectException(InvalidArgumentException::class);
        $view->render('../config/setup');
    }
}
