<article>
    <nav aria-label="<?php echo htmlspecialchars($localization->get('breadcrumbs'), ENT_QUOTES, 'UTF-8'); ?>"><a href="<?php echo htmlspecialchars($baseUrl . '/' . $series['slug'] . '/', ENT_QUOTES, 'UTF-8'); ?>">← <?php echo htmlspecialchars($series['title'], ENT_QUOTES, 'UTF-8'); ?></a></nav>
    <header><p class="meta"><time datetime="<?php echo $episode['date']->format('c'); ?>"><?php echo htmlspecialchars($localization->formatEpisodeDate($episode['date']), ENT_QUOTES, 'UTF-8'); ?></time></p><h1><?php echo htmlspecialchars($episode['title'], ENT_QUOTES, 'UTF-8'); ?></h1></header>
<?php $enclosures = $repository->enclosures($series, $episode, $baseUrl); ?>
    <audio controls preload="metadata">
<?php foreach ($enclosures as $enclosure): ?>
        <source src="<?php echo htmlspecialchars($enclosure['url'], ENT_QUOTES, 'UTF-8'); ?>" type="<?php echo htmlspecialchars($enclosure['mime_type'], ENT_QUOTES, 'UTF-8'); ?>">
<?php endforeach; ?>
        <?php echo htmlspecialchars($localization->get('audio_fallback'), ENT_QUOTES, 'UTF-8'); ?>
    </audio>
    <p class="audio-downloads"><?php echo htmlspecialchars($localization->get('download_audio'), ENT_QUOTES, 'UTF-8'); ?>:
<?php foreach ($enclosures as $index => $enclosure): ?><?php if ($index > 0): ?> · <?php endif; ?><a href="<?php echo htmlspecialchars($enclosure['url'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(strtoupper($enclosure['format']), ENT_QUOTES, 'UTF-8'); ?></a><?php endforeach; ?>
    </p>
    <div class="prose"><?php echo $episode['html']; ?></div>
</article>

