<?php

declare(strict_types=1);

namespace Runway\Request\Parameters\DataBuilder;

use Runway\Request\Parameters\DTO\FileDTO;

class FileParametersDataBuilder implements IFileParametersDataBuilder {
    public function buildFileParameter(array $rawData): FileDTO {
        return new FileDTO(
            name: $this->getString($rawData, 'name'),
            type: $this->getString($rawData, 'type'),
            size: is_numeric($rawData['size'] ?? null) ? (int)$rawData['size'] : 0,
            tmpName: $this->getString($rawData, 'tmp_name'),
            error: $this->getString($rawData, 'error'),
            fullPath: $this->getString($rawData, 'full_path'),
        );
    }

    /**
     * @param array<mixed> $rawData
     */
    protected function getString(array $rawData, string $key): string {
        $value = $rawData[$key] ?? '';

        return is_scalar($value) ? (string)$value : '';
    }
}
