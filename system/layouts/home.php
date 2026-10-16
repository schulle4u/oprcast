<header class="hero prose">
    <h1><?php echo htmlspecialchars($homePage['title'] ?? $config['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
<?php if ($homePage !== null): ?>
    <?php echo $homePage['html']; ?>
<?php endif; ?>
</header>
<?php if ($seriesList === []): ?>
<section class="empty-state" aria-labelledby="empty-title">
    <h2 id="empty-title"><?php echo htmlspecialchars($localization->get('no_podcasts_title'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <p><?php echo $localization->get('no_podcasts_help'); ?></p>
</section>
<?php else: ?>
<section aria-labelledby="series-title">
    <h2 id="series-title"><?php echo htmlspecialchars($localization->get('podcast_series'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <div class="card-grid">
<?php foreach ($seriesList as $item): ?>
        <article class="card">
            <?php if ($image = $repository->mediaUrl($item, 'image', $baseUrl)): ?><img src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="160" height="160"><?php endif; ?>
            <div><h3><a href="<?php echo htmlspecialchars($baseUrl . '/' . $item['slug'] . '/', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></a></h3>
            <p><?php echo htmlspecialchars((string) ($item['description'] ?? $markdown->text($item['body'])), ENT_QUOTES, 'UTF-8'); ?></p></div>
        </article>
<?php endforeach; ?>
    </div>
</section>
<?php if ($episodes !== []): ?>
<section aria-labelledby="episodes-title">
    <h2 id="episodes-title"><?php echo htmlspecialchars($localization->get('all_episodes'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <p class="feed-links"><a href="<?php echo htmlspecialchars($baseUrl . '/feed.xml', ENT_QUOTES, 'UTF-8'); ?>">RSS</a></p>
    <ol class="episode-list">
<?php foreach ($episodes as $item): ?>
        <li><article><h3><a href="<?php echo htmlspecialchars($baseUrl . '/' . $item['series_slug'] . '/' . $item['slug'] . '/', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></a></h3><p class="meta"><time datetime="<?php echo $item['date']->format('c'); ?>"><?php echo htmlspecialchars($localization->formatEpisodeDate($item['date']), ENT_QUOTES, 'UTF-8'); ?></time> · <a href="<?php echo htmlspecialchars($baseUrl . '/' . $item['series_slug'] . '/', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $item['series_title'], ENT_QUOTES, 'UTF-8'); ?></a></p><p><?php echo htmlspecialchars((string) ($item['description'] ?? $markdown->text($item['body'])), ENT_QUOTES, 'UTF-8'); ?></p></article></li>
<?php endforeach; ?>
    </ol>
</section>
<?php endif; ?>
<?php endif; ?>

