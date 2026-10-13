<article>
    <header class="podcast-header">
        <?php if ($image = $repository->mediaUrl($series, 'image', $baseUrl)): ?><img src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" alt="Cover von <?php echo htmlspecialchars($series['title'], ENT_QUOTES, 'UTF-8'); ?>" width="320" height="320"><?php endif; ?>
        <div><p class="eyebrow">Podcast</p><h1><?php echo htmlspecialchars($series['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
        <p><?php echo htmlspecialchars((string) ($series['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="feed-links"><a href="<?php echo htmlspecialchars($baseUrl . '/' . $series['slug'] . '/feed.xml', ENT_QUOTES, 'UTF-8'); ?>">RSS</a> · <a href="<?php echo htmlspecialchars($baseUrl . '/' . $series['slug'] . '/feed.atom', ENT_QUOTES, 'UTF-8'); ?>">Atom</a> · <a href="<?php echo htmlspecialchars($baseUrl . '/' . $series['slug'] . '/feed.json', ENT_QUOTES, 'UTF-8'); ?>">JSON Feed</a></p></div>
    </header>
    <div class="prose"><?php echo $series['html']; ?></div>
</article>
<section aria-labelledby="episodes-title">
    <h2 id="episodes-title">Episoden</h2>
<?php if ($episodes === []): ?><p>Noch keine Episoden veröffentlicht.</p><?php else: ?>
    <ol class="episode-list">
<?php foreach ($episodes as $item): ?>
        <li><article><p class="meta"><time datetime="<?php echo $item['date']->format('c'); ?>"><?php echo $item['date']->format('d.m.Y'); ?></time></p><h3><a href="<?php echo htmlspecialchars($baseUrl . '/' . $series['slug'] . '/' . $item['slug'] . '/', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></a></h3><p><?php echo htmlspecialchars((string) ($item['description'] ?? $markdown->text($item['body'])), ENT_QUOTES, 'UTF-8'); ?></p></article></li>
<?php endforeach; ?>
    </ol>
<?php endif; ?>
</section>

