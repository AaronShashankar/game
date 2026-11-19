<?php
// Pure rule functions (no HTTP).

use App\Services\AchievementService;
use App\Services\ChallengeService;
use App\Services\GameRules;

return function (TestKit $t): void {
    $t->test('level curve: level L starts at 50·L·(L−1) XP', function () use ($t) {
        $t->eq(1, GameRules::levelForXp(0));
        $t->eq(1, GameRules::levelForXp(99));
        $t->eq(2, GameRules::levelForXp(100));
        $t->eq(3, GameRules::levelForXp(300));
        $t->eq(10, GameRules::levelForXp(GameRules::xpForLevel(10)));
    });

    $t->test('gate coins: full on 1st try, half on 2nd, none after; combo capped; doubler doubles', function () use ($t) {
        $t->eq(10, GameRules::gateCoins(1, 1, false));
        $t->eq(5, GameRules::gateCoins(2, 1, false));
        $t->eq(0, GameRules::gateCoins(3, 1, false));
        $t->eq(20, GameRules::gateCoins(1, 50, false));
        $t->eq(20, GameRules::gateCoins(1, 1, true));
    });

    $t->test('review intervals grow and end after the last box', function () use ($t) {
        $t->eq(1, GameRules::reviewInterval(1));
        $t->eq(30, GameRules::reviewInterval(5));
        $t->eq(null, GameRules::reviewInterval(6));
    });

    $t->test('every difficulty defines pickups, and the daily difficulty exists', function () use ($t) {
        foreach (GameRules::DIFFICULTIES as $name => $cfg) $t->ok(($cfg['pickups'] ?? 0) > 0, "$name has no pickups");
        $t->ok(isset(GameRules::DIFFICULTIES[GameRules::DAILY_DIFFICULTY]));
        $t->eq(15, GameRules::pickupCoins(3));
    });

    $t->test('share codes use the unambiguous alphabet only', function () use ($t) {
        $t->ok(ChallengeService::isValidCode('ABCD2345'));
        $t->ok(!ChallengeService::isValidCode('ABCD234O'), 'O is excluded');
        $t->ok(!ChallengeService::isValidCode('abcd2345'), 'codes are upper case');
        $t->ok(!ChallengeService::isValidCode('ABC'), 'wrong length');
    });

    $t->test('achievement catalogue entries are complete', function () use ($t) {
        foreach (AchievementService::CATALOGUE as $code => $entry) {
            $t->eq(3, count($entry), "$code needs name, description, icon");
        }
        $t->ok(count(AchievementService::CATALOGUE) >= 13);
    });
};
