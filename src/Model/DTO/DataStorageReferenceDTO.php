<?php

declare(strict_types=1);

namespace Runway\Model\DTO;

readonly class DataStorageReferenceDTO {
    public function __construct(
        public string $propName,
        public string $refModel,
        public string $refProp,
        public array  $refOrderBy = [],
        public bool   $isDefaultGetter = true,
        public bool   $isDefaultSetter = true
    ) {}
}