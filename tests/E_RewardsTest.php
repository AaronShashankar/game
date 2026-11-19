<?php
// Shop perks and cosmetics, streak freezes, weekly prizes, achievements.

use App\Services\GameRules;

return function (TestKit $t): void {
    $t->test('streak freezes: hold at most 2', function () use ($t) {
        $p = $t->player();
        $t->exec('UPDATE users SET coins = 1000 WHERE id = ?', [$p->id]);
        $t->exec("INSERT INTO ledger (user_id, coins_delta, reason, ref) VALUES (?, 900, 'admin_grant', 'test')", [$p->id]);
        $p->ok('POST', '/api/shop/buy', ['item' => 'streakFreeze']);
        $p->ok('POST', '/api/shop/buy', ['item' => 'streakFreeze']);
        $t->fails('MAX_OWNED', $p->request('POST', '/api/shop/buy', ['item' => 'streakFreeze']));
        $t->eq(2, $p->ok('GET', '/api/me')['daily']['streak_freezes']);
        $t->assertLedgerBalanced($p->id);
    });

    $t->test('check-in: freezes cover missed days, or the streak resets', function () use ($t) {
        $p = $t->player();
        $t->exec("INSERT INTO inventory (user_id, item_code, qty) VALUES (?, 'streakFreeze', 2)", [$p->id]);

        // Last check-in 2 days ago (1 missed day), 5-day streak: one freeze is used.
        $t->exec('UPDATE users SET last_checkin = CURDATE() - INTERVAL 2 DAY, streak = 5 WHERE id = ?', [$p->id]);
        $r = $p->ok('POST', '/api/checkin');
        $t->eq(6, $r['streak']);
        $t->eq(1, $r['freezes_used']);
        $t->eq(1, $r['wallet']['inventory']['streakFreeze']);

        // 3 missed days with 1 freeze: the streak resets and the freeze is kept.
        $t->exec('UPDATE users SET last_checkin = CURDATE() - INTERVAL 4 DAY, streak = 6 WHERE id = ?', [$p->id]);
        $t->exec("DELETE FROM ledger WHERE user_id = ? AND reason = 'checkin'", [$p->id]);   // allow today's check-in again
        $t->exec('UPDATE users SET coins = (SELECT SUM(coins_delta) FROM ledger WHERE user_id = ?) WHERE id = ?', [$p->id, $p->id]);
        $r = $p->ok('POST', '/api/checkin');
        $t->eq(1, $r['streak']);
        $t->eq(0, $r['freezes_used']);
        $t->eq(1, $r['wallet']['inventory']['streakFreeze']);
        $t->assertLedgerBalanced($p->id);
    });

    $t->test('cosmetics: buying equips, equipping needs ownership, null resets', function () use ($t) {
        $p = $t->player();
        $t->fails('NOT_OWNED', $p->request('POST', '/api/shop/equip', ['kind' => 'theme', 'item' => 'space']));
        $t->exec('UPDATE users SET coins = 2000 WHERE id = ?', [$p->id]);
        $t->exec("INSERT INTO ledger (user_id, coins_delta, reason, ref) VALUES (?, 1900, 'admin_grant', 'test')", [$p->id]);
        $w = $p->ok('POST', '/api/shop/buy', ['item' => 'forest'])['wallet'];
        $t->eq('forest', $w['equipped']['theme']);
        $t->fails('ALREADY_OWNED', $p->request('POST', '/api/shop/buy', ['item' => 'forest']));
        $t->fails('NOT_FOUND', $p->request('POST', '/api/shop/equip', ['kind' => 'skin', 'item' => 'forest']));
        $t->eq(null, $p->ok('POST', '/api/shop/equip', ['kind' => 'theme', 'item' => ''])['wallet']['equipped']['theme']);
        $t->eq('forest', $p->ok('POST', '/api/shop/equip', ['kind' => 'theme', 'item' => 'forest'])['wallet']['equipped']['theme']);
        $t->assertLedgerBalanced($p->id);
    });

    $t->test('weekly prizes: last week\'s top 3 are paid once', function () use ($t) {
        $players = [$t->player('wk'), $t->player('wk'), $t->player('wk'), $t->player('wk')];
        $week = $t->scalar('SELECT CURDATE() - INTERVAL WEEKDAY(CURDATE()) DAY - INTERVAL 7 DAY');
        $t->exec('DELETE FROM weekly_rewards WHERE week_start = ?', [$week]);
        $t->exec('DELETE FROM weekly_settlements WHERE week_start = ?', [$week]);
        foreach ([400, 300, 200, 100] as $i => $xp) {
            $t->exec("INSERT INTO ledger (user_id, xp_delta, reason, ref, created_at) VALUES (?, ?, 'gate', 'test-week', ? + INTERVAL 2 DAY)",
                     [$players[$i]->id, $xp, $week]);
            $t->exec('UPDATE users SET xp = xp + ? WHERE id = ?', [$xp, $players[$i]->id]);
        }
        // An admin grant last week must not count.
        $t->exec("INSERT INTO ledger (user_id, xp_delta, reason, ref, created_at) VALUES (?, 99999, 'admin_grant', 'test-week', ? + INTERVAL 1 DAY)",
                 [$players[3]->id, $week]);
        $t->exec('UPDATE users SET xp = xp + 99999 WHERE id = ?', [$players[3]->id]);

        $me = $players[1]->ok('GET', '/api/me');
        $t->eq([$players[0]->username, $players[1]->username, $players[2]->username], array_column($me['weekly']['winners'], 'username'));
        $t->eq(['rank' => 2, 'coins' => GameRules::WEEKLY_PRIZES[2]], $me['weekly']['you']);
        $players[0]->ok('GET', '/api/me');   // a second visit must not pay again
        $t->eq(1, (int) $t->scalar("SELECT COUNT(*) FROM ledger WHERE user_id = ? AND reason = 'weekly_prize'", [$players[0]->id]));
        $t->eq(0, (int) $t->scalar("SELECT COUNT(*) FROM ledger WHERE user_id = ? AND reason = 'weekly_prize'", [$players[3]->id]));
        foreach ($players as $p) $t->assertLedgerBalanced($p->id);
    });

    $t->test('achievements are listed with unlock state, and level rules unlock on credit', function () use ($t) {
        $p = $t->player();
        $t->exec('UPDATE users SET xp = 4600, level = 10 WHERE id = ?', [$p->id]);
        $t->exec("INSERT INTO ledger (user_id, xp_delta, reason, ref) VALUES (?, 4600, 'admin_grant', 'test')", [$p->id]);
        $t->exec('UPDATE users SET last_checkin = CURDATE() - INTERVAL 1 DAY, streak = 9 WHERE id = ?', [$p->id]);
        $r = $p->ok('POST', '/api/checkin');
        $codes = array_column($r['new_achievements'], 'code');
        sort($codes);
        $t->eq(['level_10', 'level_5', 'streak_10'], $codes);
        $all = $p->ok('GET', '/api/me')['achievements'];
        $t->eq(count(App\Services\AchievementService::CATALOGUE), count($all));
        $t->eq(3, count(array_filter($all, fn ($a) => $a['unlocked_at'] !== null)));
    });
};
