<?php

namespace Runway\Request\Parameters\DataBuilder;

use Runway\Request\Parameters\DTO\FileDTO;

interface IFileParametersDataBuilder {
    /**
     * @param array<mixed> $rawData A file of $_FILES: name, type, size, tmp_name, error, full_path.
     */
    public function buildFileParameter(array $rawData): FileDTO;
}
