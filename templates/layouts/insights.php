<?php

declare(strict_types=1);

$locale = $collection['locale'];

$queryUrl = static function (int $page) use ($collection, $filters): string {
    return $collection['url'] . '?' . http_build_query([...$filters, 'page' => $page], encoding_type: PHP_QUERY_RFC3986);
};
?>
<!doctype html>
<html lang="<?= $context->escape($collection['locale']) ?>">

<head><?= $context->partial('head', ['seo' => $seo, 'assets' => $assets]) ?>
    <style>
        .insights__hero {
            padding: 11rem 0 6rem;
            background: radial-gradient(circle at 80% 20%, rgba(83, 255, 189, .12), transparent 25%), #07110f;
            color: #f3faf7
        }

        .insights__hero p {
            color: #60dfaf;
            font: 700 .68rem ui-monospace, monospace;
            letter-spacing: .13em;
            text-transform: uppercase
        }

        .insights__hero h1 {
            max-width: 12ch;
            margin: 1.5rem 0;
            font-size: clamp(3.5rem, 8vw, 7rem);
            line-height: .94;
            letter-spacing: -.065em
        }

        .insights__hero div div {
            max-width: 36rem;
            color: #8fa39b;
            line-height: 1.7
        }

        .insights__list {
            padding-top: 2rem;
            padding-bottom: 7rem
        }

        .insights__list article {
            display: grid;
            grid-template-columns: 7rem 1fr;
            gap: 2rem;
            padding: 3.5rem 0;
            border-bottom: 1px solid #ced8d4
        }

        .insights__list article>span {
            color: #4A5450;
            font: 600 .7rem ui-monospace, monospace
        }

        .insights__list article p {
            color: #0E5D44;
            font: 650 .64rem ui-monospace, monospace;
            letter-spacing: .12em
        }

        .insights__list h2 {
            max-width: 24ch;
            margin: .8rem 0 1.5rem;
            font-size: clamp(2rem, 4vw, 3.7rem);
            line-height: 1.05;
            letter-spacing: -.05em
        }

        .insights__list h2 a {
            color: #0b1713;
            text-decoration: none
        }

        .insights__list article div>a {
            color: #52645d;
            font-size: .78rem;
            font-weight: 700;
            text-decoration: none
        }

        .pagination {
            display: flex;
            justify-content: space-between;
            padding-top: 3rem
        }

        @media(max-width:620px) {
            .insights__list article {
                grid-template-columns: 2rem 1fr
            }
        }
    </style>
</head>

<body class="insights-page">
    <?= $context->partial('navigation', ['menus' => $navigation]) ?>
    <main id="main-content" class="insights">
        <header class="insights__hero">
            <div class="container">
                <p>FlatFile CMS · Engineering journal</p>
                <h1><?= $context->escape($collection['title']) ?></h1>
                <div>
                    <?= $locale === 'pl' ? 'Architektura, bezpieczeństwo i decyzje projektowe stojące za lekkim CMS-em w PHP.' : 'Architecture, security and the design decisions behind a lightweight PHP CMS.' ?>
                </div>
            </div>
        </header>
        <section class="container insights__list"><?php if ($items === []): ?>
                <p><?= $locale === 'pl' ? 'Brak artykułów.' : 'No articles found.' ?></p>
            <?php else: ?>     <?php foreach ($items as $index => $item): ?>
                    <article><span>0<?= $context->escape((string) ($index + 1)) ?></span>
                        <div>
                            <p>ENGINEERING NOTE</p>
                            <h2><a href="<?= $context->escape($item['url']) ?>"><?= $context->escape($item['title']) ?></a></h2>
                            <a href="<?= $context->escape($item['url']) ?>"><?= $locale === 'pl' ? 'Czytaj artykuł' : 'Read article' ?>
                                →</a>
                        </div>
                    </article><?php endforeach; ?><?php endif; ?><?php if ($pagination['totalPages'] > 1): ?>
                <nav class="pagination" aria-label="Pagination"><?php if ($pagination['page'] > 1): ?><a rel="prev"
                            href="<?= $context->escape($queryUrl($pagination['page'] - 1)) ?>"><?= $locale === 'pl' ? 'Poprzednia' : 'Previous' ?></a><?php endif; ?><span><?= $pagination['page'] ?>
                        /
                        <?= $pagination['totalPages'] ?></span><?php if ($pagination['page'] < $pagination['totalPages']): ?><a
                            rel="next"
                            href="<?= $context->escape($queryUrl($pagination['page'] + 1)) ?>"><?= $locale === 'pl' ? 'Następna' : 'Next' ?></a><?php endif; ?>
                </nav><?php endif; ?>
        </section>
    </main>
    <?= $context->partial('footer', ['menus' => $navigation]) ?>
</body>

</html>