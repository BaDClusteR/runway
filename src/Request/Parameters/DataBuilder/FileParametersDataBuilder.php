<?php

declare(strict_types=1);

namespace Runway\Request\Parameters\DataBuilder;

use Runway\Request\Parameters\DTO\FileDTO;

class FileParametersDataBuilder implements IFileParametersDataBuilder {
    public function buildFileParameter(array $rawData): FileDTO {
        return new FileDTO(
            name: (string)($rawData['name'] ?? ''),
            type: (string)($rawData['type'] ?? ''),
            size: (int)($rawData['size'] ?? 0),
            tmpName: (string)($rawData['tmp_name'] ?? ''),
            error: (string)($rawData['error'] ?? ''),
            fullPath: (string)($rawData['full_path'] ?? ''),
        );
    }
}