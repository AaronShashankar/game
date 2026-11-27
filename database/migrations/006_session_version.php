<?php
// Session version: a counter on each account that is copied into the session
// at login. Changing a password bumps it, so every other signed-in device no
// longer matches and is signed out on its next request (see Auth::check).

return function (PDO $db, Migrator $m): void {
    $m->addColumn('users', 'auth_version', 'INT NOT NULL DEFAULT 0');
    $m->addColumn('admin', 'auth_version', 'INT NOT NULL DEFAULT 0');
    $m->say('users.auth_version, admin.auth_version');
};
