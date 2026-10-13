<article>
    <nav aria-label="Brotkrumen"><a href="<?php echo htmlspecialchars($baseUrl . '/' . $series['slug'] . '/', ENT_QUOTES, 'UTF-8'); ?>">← <?php echo htmlspecialchars($series['title'], ENT_QUOTES, 'UTF-8'); ?></a></nav>
    <header><p class="meta"><time datetime="<?php echo $episode['date']->format('c'); ?>"><?php echo $episode['date']->format('d.m.Y'); ?></time></p><h1><?php echo htmlspecialchars($episode['title'], ENT_QUOTES, 'UTF-8'); ?></h1></header>
<?php if ($audio = $repository->mediaUrl($episode, 'audio', $baseUrl)): ?>
    <audio controls preload="metadata" src="<?php echo htmlspecialchars($audio, ENT_QUOTES, 'UTF-8'); ?>">Dein Browser unterstützt den Audioplayer nicht. <a href="<?php echo htmlspecialchars($audio, ENT_QUOTES, 'UTF-8'); ?>">Audio herunterladen</a>.</audio>
<?php endif; ?>
    <div class="prose"><?php echo $episode['html']; ?></div>
</article>

