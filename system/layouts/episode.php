<article>
    <nav aria-label="<?php echo htmlspecialchars($localization->get('breadcrumbs'), ENT_QUOTES, 'UTF-8'); ?>"><a href="<?php echo htmlspecialchars($baseUrl . '/' . $series['slug'] . '/', ENT_QUOTES, 'UTF-8'); ?>">← <?php echo htmlspecialchars($series['title'], ENT_QUOTES, 'UTF-8'); ?></a></nav>
    <header><p class="meta"><time datetime="<?php echo $episode['date']->format('c'); ?>"><?php echo htmlspecialchars($localization->formatEpisodeDate($episode['date']), ENT_QUOTES, 'UTF-8'); ?></time></p><h1><?php echo htmlspecialchars($episode['title'], ENT_QUOTES, 'UTF-8'); ?></h1></header>
<?php if ($audio = $repository->mediaUrl($episode, 'audio', $baseUrl)): ?>
    <audio controls preload="metadata" src="<?php echo htmlspecialchars($audio, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($localization->get('audio_fallback'), ENT_QUOTES, 'UTF-8'); ?> <a href="<?php echo htmlspecialchars($audio, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($localization->get('download_audio'), ENT_QUOTES, 'UTF-8'); ?></a>.</audio>
<?php endif; ?>
    <div class="prose"><?php echo $episode['html']; ?></div>
</article>

