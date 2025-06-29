<?php

declare(strict_types=1);

namespace Runway\Request\Parameters\DTO;

readonly class FileDTO {
    public function __construct(
        private string $name,
        private string $type,
        private int    $size,
        private string $tmpName,
        private string $error,
        private string $fullPath,
    ) {}

    public function getName(): string {
        return $this->name;
    }

    public function getType(): string {
        return $this->type;
    }

    public function getSize(): int {
        return $this->size;
    }

    public function getTmpName(): string {
        return $this->tmpName;
    }

    public function getError(): string {
        return $this->error;
    }

    public function getFullPath(): string {
        return $this->fullPath;
    }

    public function getContent(): string {
        return (string)file_get_contents($this->tmpName);
    }
}