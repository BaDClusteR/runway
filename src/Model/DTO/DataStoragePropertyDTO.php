<?php

declare(strict_types=1);

namespace Runway\Model\DTO;

class DataStoragePropertyDTO {
    public function __construct(
        protected string $propName = "",
        protected string $propType = "",
        protected string $column = "",
        protected bool   $isPrimary = false,
        protected string $dataStorageType = "",
        protected bool   $defaultGetter = true,
        protected bool   $defaultSetter = true
    ) {}

    public function getPropName(): string {
        return $this->propName;
    }

    public function setPropName(string $propName): static {
        $this->propName = $propName;

        return $this;
    }

    public function getPropType(): string {
        return $this->propType;
    }

    public function setPropType(string $propType): static {
        $this->propType = $propType;

        return $this;
    }

    public function getColumn(): string {
        return $this->column;
    }

    public function setColumn(string $column): static {
        $this->column = $column;

        return $this;
    }

    public function isPrimary(): bool {
        return $this->isPrimary;
    }

    public function setIsPrimary(bool $isPrimary): static {
        $this->isPrimary = $isPrimary;

        return $this;
    }

    public function getDataStorageType(): string {
        return $this->dataStorageType;
    }

    public function setDataStorageType(string $dataStorageType): static {
        $this->dataStorageType = $dataStorageType;

        return $this;
    }

    public function isDefaultGetter(): bool {
        return $this->defaultGetter;
    }

    public function setDefaultGetter(bool $defaultGetter): static {
        $this->defaultGetter = $defaultGetter;

        return $this;
    }

    public function isDefaultSetter(): bool {
        return $this->defaultSetter;
    }

    public function setDefaultSetter(bool $defaultSetter): static {
        $this->defaultSetter = $defaultSetter;

        return $this;
    }
}