<?php
$siteTitle = $config['title'];
$documentTitle = isset($title) ? $title . ' – ' . $siteTitle : (($episode['title'] ?? $series['title'] ?? $page['title'] ?? null) ? ($episode['title'] ?? $series['title'] ?? $page['title']) . ' – ' . $siteTitle : $siteTitle);
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars($config['language'], ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="description" content="<?php echo htmlspecialchars($config['description'], ENT_QUOTES, 'UTF-8'); ?>">
    <title><?php echo htmlspecialchars((string) $documentTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>/assets/theme.css">
<?php if (isset($series)): ?>
    <link rel="alternate" type="application/rss+xml" title="<?php echo htmlspecialchars($series['title'], ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo htmlspecialchars($baseUrl . '/' . $series['slug'] . '/feed.xml', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="alternate" type="application/feed+json" title="<?php echo htmlspecialchars($series['title'], ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo htmlspecialchars($baseUrl . '/' . $series['slug'] . '/feed.json', ENT_QUOTES, 'UTF-8'); ?>">
<?php endif; ?>
</head>
<body>
<a class="skip-link" href="#main">Zum Inhalt springen</a>
<header class="site-header">
    <a class="brand" href="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>/"><?php echo htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8'); ?></a>
<?php if ($pages !== []): ?>
    <nav aria-label="Hauptnavigation"><ul>
<?php foreach ($pages as $navigationPage): ?>
        <li><a href="<?php echo htmlspecialchars($baseUrl . '/pages/' . $navigationPage['slug'] . '/', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($navigationPage['title'], ENT_QUOTES, 'UTF-8'); ?></a></li>
<?php endforeach; ?>
    </ul></nav>
<?php endif; ?>
</header>
<main id="main" class="container"><?php echo $content; ?></main>
<footer class="site-footer"><p>Veröffentlicht mit OPRCast.</p></footer>
</body>
</html>

