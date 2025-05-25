<?php

declare(strict_types=1);

namespace Runway\DataStorage\DTO;

readonly class DBConnectOptionsDTO {
    public function __construct(
        protected string $user,
        protected string $password,
        protected string $dbName,
        protected string $host,
        protected int    $port,
        protected string $tableNamePrefix,
        protected string $encoding
    ) {}

    public function getUser(): string {
        return $this->user;
    }

    public function getPassword(): string {
        return $this->password;
    }

    public function getDbName(): string {
        return $this->dbName;
    }

    public function getHost(): string {
        return $this->host;
    }

    public function getPort(): int {
        return $this->port;
    }

    public function getTableNamePrefix(): string {
        return $this->tableNamePrefix;
    }

    public function getEncoding(): string {
        return $this->encoding;
    }
}