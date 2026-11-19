<?php
// One-time conversion of data from the old schema. No-op on a fresh database.
//
// 1. XP becomes a lifetime total. The old game stored "XP into the current
//    level" and reset it on level-up, which made the XP leaderboard wrong.
// 2. Every existing player gets an opening ledger entry (so ledger sums match
//    balances) and the starter power-ups the old game handed out on every load.
// 3. Tables from the old dual-identity design (player/leaderboard/game_session…)
//    are renamed to legacy_* — kept for reference, no longer used. Drop them
//    yourself once you're happy with the migration.

use App\Services\GameRules;

return function (PDO $db, Migrator $m): void {
    // 1. Lifetime XP: total = 50·L·(L-1) + xp-into-level.
    $n = $db->exec("UPDATE users SET xp = 50 * level * (level - 1) + xp WHERE level > 1");
    $m->say("converted XP to lifetime totals for $n player(s)");
    $db->exec("UPDATE users SET level = GREATEST(1, FLOOR((1 + SQRT(1 + 8 * xp / 100)) / 2))");

    // 2. Opening balances + starter inventory.
    $db->exec("INSERT IGNORE INTO ledger (user_id, coins_delta, xp_delta, reason, ref)
               SELECT id, coins, xp, 'opening_balance', 'migration-002' FROM users");
    $ins = $db->prepare("INSERT IGNORE INTO inventory (user_id, item_code, qty)
                         SELECT id, ?, ? FROM users WHERE role = 'user'");
    foreach (GameRules::POWERUPS as $code) {
        $ins->execute([$code, GameRules::STARTER_POWERUPS]);
    }

    // Old setup scripts also created admins as rows in users (role='admin').
    // Admins log in from the admin table; disable those rows so they can't
    // be used as player accounts.
    $n = $db->exec("UPDATE users SET is_active = 0 WHERE role = 'admin'");
    if ($n) $m->say("disabled $n admin row(s) in users (admins use the admin table)");

    // 3. Retire the old tables.
    foreach (['leaderboard', 'game_session', 'game_sessions', 'player', 'maze', 'games', 'rewards', 'user_achievements'] as $t) {
        if ($m->tableExists($t) && !$m->tableExists("legacy_$t")) {
            $db->exec("RENAME TABLE `$t` TO `legacy_$t`");
            $m->say("renamed $t → legacy_$t");
        }
    }
};
