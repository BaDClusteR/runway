<?php

declare(strict_types=1);

namespace Runway\Tests\Support;

use Runway\DataStorage\IDataStorageDriver;

/**
 * The tables of the fixture models (tests/Fixture/Model) in SQLite: "{name}" gets the DB_PREFIX of test.env. The
 * columns of the not nullable props have defaults.
 */
final class Database {
    public static function createTables(IDataStorageDriver $driver): void {
        $driver->execute(
            "CREATE TABLE `{users}` (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL DEFAULT '',"
            . " created_at INTEGER, is_active INTEGER NOT NULL DEFAULT 0, tags TEXT NOT NULL DEFAULT '[]', score REAL,"
            . " nick TEXT NOT NULL DEFAULT '', password TEXT NOT NULL DEFAULT '')"
        );
        $driver->execute("CREATE TABLE `{posts}` (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL DEFAULT '', author_id INTEGER)");
        $driver->execute("CREATE TABLE `{tags}` (code TEXT PRIMARY KEY, label TEXT NOT NULL DEFAULT '')");
        $driver->execute("CREATE TABLE `{nodes}` (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL DEFAULT '', partner_id INTEGER)");
    }
}
