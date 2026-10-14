<?php
$siteTitle = $config['title'];
$documentTitle = isset($title) ? $title . ' – ' . $siteTitle : (($episode['title'] ?? $series['title'] ?? $page['title'] ?? null) ? ($episode['title'] ?? $series['title'] ?? $page['title']) . ' – ' . $siteTitle : $siteTitle);
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars($localization->languageCode(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="description" content="<?php echo htmlspecialchars($localization->get('site_description'), ENT_QUOTES, 'UTF-8'); ?>">
    <title><?php echo htmlspecialchars((string) $documentTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>/assets/theme.css">
<?php if (isset($series)): ?>
    <link rel="alternate" type="application/rss+xml" title="<?php echo htmlspecialchars($series['title'], ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo htmlspecialchars($baseUrl . '/' . $series['slug'] . '/feed.xml', ENT_QUOTES, 'UTF-8'); ?>">
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
<?php foreach ($pages as $navigationPage): ?>
        <li><a href="<?php echo htmlspecialchars($baseUrl . '/pages/' . $navigationPage['slug'] . '/', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($navigationPage['title'], ENT_QUOTES, 'UTF-8'); ?></a></li>
<?php endforeach; ?>
    </ul></nav>
<?php endif; ?>
</header>
<main id="main" class="container"><?php echo $content; ?></main>
<footer class="site-footer"><p><?php echo htmlspecialchars($localization->get('published_with'), ENT_QUOTES, 'UTF-8'); ?> (<a href="https://m45.dev">M45Development</a>).</p></footer>
</body>
</html>

