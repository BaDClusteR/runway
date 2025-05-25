<?php

namespace Runway\Request\Parameters\DataBuilder;

use Runway\Request\Parameters\DTO\FileDTO;

interface IFileParametersDataBuilder {
    public function buildFileParameter(array $rawData): FileDTO;
}