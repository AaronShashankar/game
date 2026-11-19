<?php
/**
 * Opens every page: <head>, stylesheet, theme, icon sprite.
 * @var string|null $title     page name ("Dashboard"); null = just "MazeQuest"
 * @var string|null $bodyClass
 * @var string|null $head      extra tags for <head>
 */
$title = $title ?? null;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ? "$title · MazeQuest" : 'MazeQuest — Learn by finding your way out') ?></title>
  <meta name="description" content="MazeQuest is an educational maze game: answer questions to open gates and reach the exit.">
  <script>try { var t = localStorage.getItem('theme'); if (t === 'dark' || t === 'light') document.documentElement.dataset.theme = t; } catch (e) {}</script>
  <link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 28 28"><rect width="28" height="28" rx="8" fill="#17171a"/><path d="M8 8h12M8 8v12h6M20 12v8M12 12h4v4" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="17" cy="20" r="2" fill="#4f46e5"/></svg>') ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
  <?= $head ?? '' ?>
  <?= app_scripts() ?>
</head>
<body<?= !empty($bodyClass) ? ' class="' . e($bodyClass) . '"' : '' ?>>
<?php require __DIR__ . '/icons.php'; ?>
