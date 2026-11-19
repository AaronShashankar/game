<?php
$title = 'Profile';
$active = 'profile';
require __DIR__ . '/../layouts/app-start.php';
$powers = [['timeFreeze', 'snowflake', 'Time freeze'], ['hint', 'search', 'Hint'], ['skip', 'skip', 'Skip gate'], ['scoreDoubler', 'trend', 'Score doubler']];
?>

<div class="page-head">
  <div>
    <h1>Profile</h1>
    <p>Your account, progress and inventory.</p>
  </div>
</div>

<div class="profile-grid">
  <div class="stack">
    <div class="card profile-id">
      <img src="" alt="Your avatar" id="avatar" class="profile-avatar">
      <h2 id="headerUserName">—</h2>
      <p class="muted" id="headerUserEmail" style="font-size:14px">—</p>
      <div class="row mt-16" style="justify-content:center">
        <button class="btn btn-secondary btn-sm" onclick="document.getElementById('avatarFile').click()"><?= icon('upload', 'icon-sm') ?>Upload photo</button>
        <button class="btn btn-ghost btn-sm" onclick="changeAvatar()"><?= icon('refresh', 'icon-sm') ?>Random</button>
      </div>
      <input type="file" id="avatarFile" accept="image/*" hidden onchange="uploadAvatar(this.files[0]); this.value = ''">
    </div>

    <div class="card">
      <div class="card-head" style="margin-bottom:16px"><div class="card-title">Progress</div></div>
      <div class="row-between" style="font-size:14px">
        <span class="muted">Level <strong id="level" style="color:var(--text)">—</strong></span>
        <span class="subtle num"><span id="currentXP">0</span> / <span id="nextLevelXP">0</span> XP · <span id="xpPercent">0%</span></span>
      </div>
      <div class="progress mt-8"><span id="xpFill"></span></div>
      <dl class="kv mt-24">
        <div><dt>Coins</dt><dd id="coins">—</dd></div>
        <div><dt>Streak</dt><dd><span id="streak">—</span> <span id="streakUnit">days</span></dd></div>
        <div><dt>Games played</dt><dd id="games">—</dd></div>
        <div><dt>Accuracy</dt><dd id="accuracy">—</dd></div>
      </dl>
    </div>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card-head"><div><div class="card-title">Account</div><div class="card-sub">Your public name and login email.</div></div></div>
      <form class="form" id="accountForm">
        <div class="form-row">
          <div class="field"><label for="username">Username</label><input class="input" type="text" id="username" autocomplete="username" required></div>
          <div class="field"><label for="email">Email</label><input class="input" type="email" id="email" autocomplete="email" required></div>
        </div>
        <div class="form-actions"><button type="submit" class="btn btn-primary">Save changes</button></div>
      </form>
    </div>

    <div class="card">
      <div class="card-head"><div><div class="card-title">Password</div><div class="card-sub">At least 6 characters.</div></div></div>
      <form class="form" id="passwordForm">
        <div class="field"><label for="currentPassword">Current password</label><input class="input" type="password" id="currentPassword" autocomplete="current-password" required></div>
        <div class="form-row">
          <div class="field"><label for="password">New password</label><input class="input" type="password" id="password" autocomplete="new-password" required minlength="6"></div>
          <div class="field"><label for="passwordConfirm">Confirm new password</label><input class="input" type="password" id="passwordConfirm" autocomplete="new-password" required></div>
        </div>
        <div class="form-actions"><button type="submit" class="btn btn-secondary">Update password</button></div>
      </form>
    </div>

    <div class="card">
      <div class="card-head"><div class="card-title">Power-ups</div><a class="btn btn-ghost btn-sm" href="<?= url('shop') ?>">Shop<?= icon('arrow-right', 'icon-sm') ?></a></div>
      <div class="inv-grid">
        <?php foreach ($powers as [$code, $ico, $label]): ?>
          <div class="inv"><?= icon($ico) ?><div><div class="inv-count" id="<?= $code ?>Count">0</div><div class="inv-name"><?= e($label) ?></div></div></div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><div class="card-title">Badges</div></div>
      <div id="badges" class="badge-list"></div>
      <p id="noBadges" class="subtle" style="font-size:14px">No badges yet. Reach level 5, save 500 coins or keep a 10-day streak.</p>
    </div>
  </div>
</div>

<?= page_script('profile') ?>
<?php require __DIR__ . '/../layouts/app-end.php'; ?>
