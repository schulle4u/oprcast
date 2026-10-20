<?php
$siteTitle = $config['title'];
$contentTitle = $title ?? $episode['title'] ?? $series['title'] ?? $page['title'] ?? $homePage['title'] ?? null;
$documentTitle = $contentTitle !== null && $contentTitle !== $siteTitle ? $contentTitle . ' – ' . $siteTitle : $siteTitle;
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars($localization->languageCode(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="description" content="<?php echo htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8'); ?>">
    <title><?php echo htmlspecialchars((string) $documentTitle, ENT_QUOTES, 'UTF-8'); ?></title>
<?php if ($canonicalUrl !== null): ?>
    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo htmlspecialchars((string) $documentTitle, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:site_name" content="<?php echo htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8'); ?>">
<?php if ($pageImage !== null): ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($pageImage, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image:alt" content="<?php echo htmlspecialchars($pageImageAlt, ENT_QUOTES, 'UTF-8'); ?>">
<?php endif; ?>
<?php endif; ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>/assets/theme.css">
<?php if (isset($episode)): ?>
    <script src="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>/assets/player.js" defer></script>
<?php endif; ?>
<?php if (isset($series)): ?>
    <link rel="alternate" type="application/rss+xml" title="<?php echo htmlspecialchars($series['title'], ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo htmlspecialchars($baseUrl . '/' . $series['slug'] . '/feed.xml', ENT_QUOTES, 'UTF-8'); ?>">
<?php foreach ($repository->seriesFormats($series) as $mediaFormat): ?>
    <link rel="alternate" type="application/rss+xml" title="<?php echo htmlspecialchars($series['title'] . ' (' . strtoupper($mediaFormat) . ')', ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo htmlspecialchars($baseUrl . '/' . $series['slug'] . '/feed.' . $mediaFormat . '.xml', ENT_QUOTES, 'UTF-8'); ?>">
<?php endforeach; ?>
    <link rel="alternate" type="application/feed+json" title="<?php echo htmlspecialchars($series['title'], ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo htmlspecialchars($baseUrl . '/' . $series['slug'] . '/feed.json', ENT_QUOTES, 'UTF-8'); ?>">
<?php else: ?>
    <link rel="alternate" type="application/rss+xml" title="<?php echo htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo htmlspecialchars($baseUrl . '/feed.xml', ENT_QUOTES, 'UTF-8'); ?>">
<?php endif; ?>
</head>
<body>
<a class="skip-link" href="#main"><?php echo htmlspecialchars($localization->get('skip_to_content'), ENT_QUOTES, 'UTF-8'); ?></a>
<header class="site-header">
    <a class="brand" href="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>/"><?php echo htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8'); ?></a>
<?php if ($pages !== []): ?>
    <nav aria-label="<?php echo htmlspecialchars($localization->get('main_navigation'), ENT_QUOTES, 'UTF-8'); ?>"><ul>
    <li><a href="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>/"><?php echo htmlspecialchars($localization->get('main_navigation_home'), ENT_QUOTES, 'UTF-8'); ?></a></li>
<?php foreach ($pages as $navigationPage): ?>
        <li><a href="<?php echo htmlspecialchars($baseUrl . '/pages/' . $navigationPage['slug'] . '/', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($navigationPage['title'], ENT_QUOTES, 'UTF-8'); ?></a></li>
<?php endforeach; ?>
    </ul></nav>
<?php endif; ?>
</header>
<main id="main" class="container"><?php echo $content; ?></main>
<footer class="site-footer"><p><a href="https://github.com/schulle4u/oprcast"><?php echo htmlspecialchars($localization->get('published_with'), ENT_QUOTES, 'UTF-8'); ?></a> (<a href="https://m45.dev">M45Development</a>).</p></footer>
</body>
</html>

