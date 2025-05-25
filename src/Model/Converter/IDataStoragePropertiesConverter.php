<?php

declare(strict_types=1);

namespace Runway\Model\Converter;

interface IDataStoragePropertiesConverter {
    public function convert(string $fromType, string $toType, mixed $value): mixed;
}