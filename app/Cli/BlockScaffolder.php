<?php

declare(strict_types=1);

namespace FlatFileCms\Cli;

use FlatFileCms\Domain\Content\Slug;
use InvalidArgumentException;
use Throwable;

final readonly class BlockScaffolder
{
    private string $blocksRoot;

    public function __construct(string $projectRoot)
    {
        $blocksRoot = realpath(rtrim($projectRoot, '/\\') . '/blocks');
        if ($blocksRoot === false || !is_dir($blocksRoot) || !is_writable($blocksRoot)) {
            throw new BlockScaffolderException('Blocks directory is unavailable or not writable.');
        }

        $this->blocksRoot = $blocksRoot;
    }

    /** @return non-empty-list<string> */
    public function create(string $requestedType, bool $withAssets = false): array
    {
        try {
            $type = Slug::fromString($requestedType)->value();
        } catch (InvalidArgumentException $exception) {
            throw new BlockScaffolderException(
                'Block type must be a lowercase ASCII slug, for example "image-with-text".',
                previous: $exception,
            );
        }

        $lock = fopen($this->blocksRoot . '/.cms-block-create.lock', 'c+b');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            if (is_resource($lock)) {
                fclose($lock);
            }

            throw new BlockScaffolderException('Unable to lock the blocks directory.');
        }

        try {
            return $this->createLocked($type, $withAssets);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return non-empty-list<string> */
    private function createLocked(string $type, bool $withAssets): array
    {
        $targetDirectory = $this->blocksRoot . '/' . $type;
        if (file_exists($targetDirectory) || is_link($targetDirectory)) {
            throw new BlockScaffolderException(sprintf('Block "%s" already exists.', $type));
        }

        $temporaryDirectory = $this->blocksRoot . '/.cms-block-' . bin2hex(random_bytes(12));
        if (!mkdir($temporaryDirectory, 0o700)) {
            throw new BlockScaffolderException('Unable to create temporary block directory.');
        }

        $files = [
            'block.yml' => $this->definition($type),
            'render.php' => $this->renderer($type),
        ];
        if ($withAssets) {
            $files['style.css'] = $this->stylesheet($type);
            $files['script.js'] = $this->script($type);
        }

        try {
            foreach ($files as $filename => $contents) {
                $this->writeFile($temporaryDirectory . '/' . $filename, $contents);
            }
            if (!chmod($temporaryDirectory, 0o750)) {
                throw new BlockScaffolderException('Unable to set block directory permissions.');
            }
            if (file_exists($targetDirectory)) {
                throw new BlockScaffolderException(sprintf('Block "%s" already exists.', $type));
            }
            if (!rename($temporaryDirectory, $targetDirectory)) {
                throw new BlockScaffolderException('Unable to publish the new block directory atomically.');
            }
        } catch (Throwable $exception) {
            $this->removeTemporaryDirectory($temporaryDirectory);
            if ($exception instanceof BlockScaffolderException) {
                throw $exception;
            }

            throw new BlockScaffolderException('Unable to create block files.', previous: $exception);
        }

        /** @var non-empty-list<string> */
        return array_map(
            static fn(string $filename): string => 'blocks/' . $type . '/' . $filename,
            array_keys($files),
        );
    }

    private function definition(string $type): string
    {
        $label = implode(' ', array_map(
            static fn(string $segment): string => ucfirst($segment),
            explode('-', $type),
        ));

        return str_replace(
            '{{ label }}',
            $label,
            <<<'YAML'
schemaVersion: 1
name:
  pl: {{ label }}
  en: {{ label }}
description:
  pl: Sekcja {{ label }}.
  en: {{ label }} section.
icon: blocks
fields:
  title:
    type: text
    required: true
    translatable: true
    minLength: 1
    maxLength: 160
    label:
      pl: Nagłówek
      en: Heading
  content:
    type: markdown
    required: false
    translatable: true
    maxLength: 10000
    label:
      pl: Treść
      en: Content
YAML,
        ) . "\n";
    }

    private function renderer(string $type): string
    {
        return str_replace(
            '{{ type }}',
            $type,
            <<<'PHP'
<?php

declare(strict_types=1);

$title = $data['title'];
$content = $data['content'] ?? '';
?>
<section class="block-{{ type }}">
    <div class="container">
        <h2><?= $context->escape($title) ?></h2>
        <?php if ($content !== ''): ?>
            <div class="block-{{ type }}__content"><?= $context->markdown($content) ?></div>
        <?php endif; ?>
    </div>
</section>
PHP,
        ) . "\n";
    }

    private function stylesheet(string $type): string
    {
        return str_replace(
            '{{ type }}',
            $type,
            <<<'CSS'
.block-{{ type }} {
    padding-block: clamp(2rem, 5vw, 5rem);
}

.block-{{ type }}__content {
    margin-block-start: 1rem;
}
CSS,
        ) . "\n";
    }

    private function script(string $type): string
    {
        return str_replace(
            '{{ type }}',
            $type,
            <<<'JS'
document.querySelectorAll('.block-{{ type }}').forEach((block) => {
    block.dataset.blockInitialized = 'true';
});
JS,
        ) . "\n";
    }

    private function writeFile(string $path, string $contents): void
    {
        $written = file_put_contents($path, $contents, LOCK_EX);
        if ($written === false || $written !== strlen($contents) || !chmod($path, 0o640)) {
            throw new BlockScaffolderException('Unable to write a generated block file.');
        }
    }

    private function removeTemporaryDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = scandir($directory);
        if ($files !== false) {
            foreach ($files as $filename) {
                if ($filename !== '.' && $filename !== '..') {
                    unlink($directory . '/' . $filename);
                }
            }
        }

        rmdir($directory);
    }
}
