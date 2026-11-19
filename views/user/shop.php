<?php
$title = 'Shop';
$active = 'shop';
require __DIR__ . '/../layouts/app-start.php';
?>

<div class="page-head">
  <div>
    <h1>Shop</h1>
    <p>Spend the coins you earn in the maze. Prices are set by the server.</p>
  </div>
  <span class="pill"><?= icon('coins') ?><span id="coinCount" class="num">—</span> coins</span>
</div>

<!-- Filled from /api/shop: prices and ownership come from the server. -->
<section class="shop-section"><h2>Power-ups</h2><div class="item-grid" data-kinds="powerup,pack"><div class="empty">Loading…</div></div></section>
<section class="shop-section"><h2>Perks</h2><div class="item-grid" data-kinds="perk"></div></section>
<section class="shop-section"><h2>Player skins</h2><div class="item-grid" data-kinds="skin"></div></section>
<section class="shop-section"><h2>Maze themes</h2><div class="item-grid" data-kinds="theme"></div></section>
<script>window.ShopRules = <?= json_encode(['streakFreezeMax' => App\Services\GameRules::STREAK_FREEZE_MAX]) ?>;</script>

<?= page_script('shop') ?>
<?php require __DIR__ . '/../layouts/app-end.php'; ?>
