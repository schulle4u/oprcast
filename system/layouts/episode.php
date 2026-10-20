<article data-episode-player data-copy-label="<?php echo htmlspecialchars($localization->get('copy_chapter_link'), ENT_QUOTES, 'UTF-8'); ?>" data-copied-label="<?php echo htmlspecialchars($localization->get('chapter_link_copied'), ENT_QUOTES, 'UTF-8'); ?>" data-copy-failed="<?php echo htmlspecialchars($localization->get('chapter_copy_failed'), ENT_QUOTES, 'UTF-8'); ?>">
    <nav aria-label="<?php echo htmlspecialchars($localization->get('breadcrumbs'), ENT_QUOTES, 'UTF-8'); ?>"><a href="<?php echo htmlspecialchars($baseUrl . '/' . $series['slug'] . '/', ENT_QUOTES, 'UTF-8'); ?>">← <?php echo htmlspecialchars($series['title'], ENT_QUOTES, 'UTF-8'); ?></a></nav>
    <header><h1><?php echo htmlspecialchars($episode['title'], ENT_QUOTES, 'UTF-8'); ?></h1><p class="meta"><time datetime="<?php echo $episode['date']->format('c'); ?>"><?php echo htmlspecialchars($localization->formatEpisodeDate($episode['date']), ENT_QUOTES, 'UTF-8'); ?></time></p></header>
<?php $enclosures = $repository->enclosures($series, $episode, $baseUrl); ?>
    <figure>
        <audio controls preload="metadata">
<?php foreach ($enclosures as $enclosure): ?>
            <source src="<?php echo htmlspecialchars($enclosure['url'], ENT_QUOTES, 'UTF-8'); ?>" type="<?php echo htmlspecialchars($enclosure['mime_type'], ENT_QUOTES, 'UTF-8'); ?>">
<?php endforeach; ?>
            <?php echo htmlspecialchars($localization->get('audio_fallback'), ENT_QUOTES, 'UTF-8'); ?>
        </audio>
        <figcaption><?php echo htmlspecialchars($localization->get('audio_caption'), ENT_QUOTES, 'UTF-8'); ?>: <?php echo htmlspecialchars($episode['title'], ENT_QUOTES, 'UTF-8'); ?></figcaption>
    </figure>
    <p class="audio-downloads"><?php echo htmlspecialchars($localization->get('download_audio'), ENT_QUOTES, 'UTF-8'); ?>:
<?php foreach ($enclosures as $index => $enclosure): ?><?php if ($index > 0): ?> · <?php endif; ?><a href="<?php echo htmlspecialchars($enclosure['url'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(strtoupper($enclosure['format']), ENT_QUOTES, 'UTF-8'); ?></a><?php endforeach; ?>
    </p>
    <div class="prose" data-show-notes><?php echo $markdown->renderEpisode($episode['body']); ?></div>
    <p class="player-status" role="status" aria-live="polite" aria-atomic="true"></p>
<?php if ($transcript = $repository->transcript($episode, $baseUrl)): ?>
    <section aria-labelledby="transcript-title" class="transcript prose">
        <h2 id="transcript-title"><?php echo htmlspecialchars($localization->get('transcript'), ENT_QUOTES, 'UTF-8'); ?></h2>
        <p><a href="<?php echo htmlspecialchars($transcript['url'], ENT_QUOTES, 'UTF-8'); ?>" download><?php echo htmlspecialchars($localization->get('download_transcript'), ENT_QUOTES, 'UTF-8'); ?> (<?php echo strtoupper($transcript['extension']); ?>)</a></p>
        <div class="transcript-text"><?php echo htmlspecialchars($transcript['text'], ENT_QUOTES, 'UTF-8'); ?></div>
    </section>
<?php endif; ?>
</article>

