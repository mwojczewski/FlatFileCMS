<?php

declare(strict_types=1);

$content = $data['content'];
?>
<section class="rich-text">
    <div class="container"><?= $context->markdown($content) ?></div>
</section>
