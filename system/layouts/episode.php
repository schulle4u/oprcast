<article data-episode-player
    data-episode-id="<?php echo htmlspecialchars((string) ($episode['guid'] ?? $baseUrl . '/' . $series['slug'] . '/' . $episode['slug'] . '/'), ENT_QUOTES, 'UTF-8'); ?>"
    data-position-label="<?php echo htmlspecialchars($localization->get('player_position'), ENT_QUOTES, 'UTF-8'); ?>"
    data-resume-label="<?php echo htmlspecialchars($localization->get('player_resumed'), ENT_QUOTES, 'UTF-8'); ?>"
    data-copy-label="<?php echo htmlspecialchars($localization->get('copy_chapter_link'), ENT_QUOTES, 'UTF-8'); ?>"
    data-copied-label="<?php echo htmlspecialchars($localization->get('chapter_link_copied'), ENT_QUOTES, 'UTF-8'); ?>"
    data-copy-failed="<?php echo htmlspecialchars($localization->get('chapter_copy_failed'), ENT_QUOTES, 'UTF-8'); ?>">
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
        <div class="player-controls" data-player-controls role="group" aria-label="<?php echo htmlspecialchars($localization->get('player_controls'), ENT_QUOTES, 'UTF-8'); ?>" hidden>
            <button type="button" data-skip="-15" disabled><?php echo htmlspecialchars($localization->get('player_back'), ENT_QUOTES, 'UTF-8'); ?></button>
            <button type="button" data-skip="30" disabled><?php echo htmlspecialchars($localization->get('player_forward'), ENT_QUOTES, 'UTF-8'); ?></button>
            <label for="playback-speed"><?php echo htmlspecialchars($localization->get('player_speed'), ENT_QUOTES, 'UTF-8'); ?></label>
            <select id="playback-speed" data-playback-speed>
<?php foreach (['0.75', '1', '1.25', '1.5', '2'] as $speed): ?>
                <option value="<?php echo $speed; ?>"<?php echo $speed === '1' ? ' selected' : ''; ?>><?php echo $speed; ?>×</option>
<?php endforeach; ?>
            </select>
        </div>
        <p class="player-status" role="status" aria-live="polite" aria-atomic="true"></p>
        <figcaption><?php echo htmlspecialchars($localization->get('audio_caption'), ENT_QUOTES, 'UTF-8'); ?>: <?php echo htmlspecialchars($episode['title'], ENT_QUOTES, 'UTF-8'); ?></figcaption>
    </figure>
    <p class="audio-downloads"><?php echo htmlspecialchars($localization->get('download_audio'), ENT_QUOTES, 'UTF-8'); ?>:
<?php foreach ($enclosures as $index => $enclosure): ?><?php if ($index > 0): ?> · <?php endif; ?><a href="<?php echo htmlspecialchars($enclosure['url'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(strtoupper($enclosure['format']), ENT_QUOTES, 'UTF-8'); ?></a><?php endforeach; ?>
    </p>
    <div class="prose" data-show-notes><?php echo $markdown->renderEpisode($episode['body']); ?></div>
<?php if ($transcript = $repository->transcript($episode, $baseUrl)): ?>
    <section aria-labelledby="transcript-title" class="transcript prose">
        <h2 id="transcript-title"><?php echo htmlspecialchars($localization->get('transcript'), ENT_QUOTES, 'UTF-8'); ?></h2>
        <p><a href="<?php echo htmlspecialchars($transcript['url'], ENT_QUOTES, 'UTF-8'); ?>" download><?php echo htmlspecialchars($localization->get('download_transcript'), ENT_QUOTES, 'UTF-8'); ?> (<?php echo strtoupper($transcript['extension']); ?>)</a></p>
        <div class="transcript-text"><?php echo htmlspecialchars($transcript['text'], ENT_QUOTES, 'UTF-8'); ?></div>
    </section>
<?php endif; ?>
<?php if ($previousEpisode !== null || $nextEpisode !== null): ?>
    <nav class="episode-navigation" aria-label="<?php echo htmlspecialchars($localization->get('episode_navigation'), ENT_QUOTES, 'UTF-8'); ?>">
        <ul>
<?php if ($previousEpisode !== null): ?>
            <li><a rel="prev" href="<?php echo htmlspecialchars($baseUrl . '/' . $series['slug'] . '/' . $previousEpisode['slug'] . '/', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($localization->get('previous_episode'), ENT_QUOTES, 'UTF-8'); ?>: <?php echo htmlspecialchars($previousEpisode['title'], ENT_QUOTES, 'UTF-8'); ?></a></li>
<?php endif; ?>
<?php if ($nextEpisode !== null): ?>
            <li><a rel="next" href="<?php echo htmlspecialchars($baseUrl . '/' . $series['slug'] . '/' . $nextEpisode['slug'] . '/', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($localization->get('next_episode'), ENT_QUOTES, 'UTF-8'); ?>: <?php echo htmlspecialchars($nextEpisode['title'], ENT_QUOTES, 'UTF-8'); ?></a></li>
<?php endif; ?>
        </ul>
    </nav>
<?php endif; ?>
</article>

