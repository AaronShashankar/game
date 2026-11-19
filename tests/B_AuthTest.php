<?php
// Sign-up, sessions and route guards.

return function (TestKit $t): void {
    $t->test('API routes need a session; pages redirect to login and remember where you were going', function () use ($t) {
        $guest = new Client($t->base);
        $t->fails('UNAUTHENTICATED', $guest->request('GET', '/api/me'), 401);
        $page = $guest->request('GET', '/game?challenge=ABCD2345');
        $t->eq(302, $page['status']);
        $t->ok(preg_match('#Location: /user\?next=%2Fgame%3Fchallenge%3DABCD2345#', $page['headers']) === 1, 'redirect keeps ?next: ' . $page['headers']);
    });

    $t->test('registration starts players with coins, power-ups and no achievements', function () use ($t) {
        $p = $t->player();
        $me = $p->ok('GET', '/api/me');
        $t->eq($p->username, $me['user']['username']);
        $t->ok($me['wallet']['coins'] > 0, 'starter coins');
        $t->eq(3, $me['wallet']['inventory']['hint'] ?? 0);
        $t->eq(0, count(array_filter($me['achievements'], fn ($a) => $a['unlocked_at'] !== null)));
        $t->eq(['skin' => null, 'theme' => null], $me['wallet']['equipped']);
        $t->assertLedgerBalanced($p->id);
    });

    $t->test('the public challenge page handles unknown codes', function () use ($t) {
        $res = (new Client($t->base))->request('GET', '/challenge?code=ZZZZZZZZ');
        $t->eq(200, $res['status']);
        $t->ok(str_contains($res['body'], 'not valid') || str_contains($res['body'], 'expired'), 'shows a not-found message');
    });
};
