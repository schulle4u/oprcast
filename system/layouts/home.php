<header class="hero">
    <p class="eyebrow">Flatfile Podcast Publisher</p>
    <h1><?php echo htmlspecialchars($config['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
    <p><?php echo htmlspecialchars($config['description'], ENT_QUOTES, 'UTF-8'); ?></p>
</header>
<?php if ($seriesList === []): ?>
<section class="empty-state" aria-labelledby="empty-title">
    <h2 id="empty-title">Noch keine Podcasts veröffentlicht</h2>
    <p>Lege für jede Serie einen Ordner mit einer <code>index.md</code> unter <code>content/</code> an.</p>
</section>
<?php else: ?>
<section aria-labelledby="series-title">
    <h2 id="series-title">Podcastserien</h2>
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
<?php endif; ?>

