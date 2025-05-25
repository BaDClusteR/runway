<?php

declare(strict_types=1);

namespace Runway\Service\Provider;

interface IConfigDirectoriesProvider {
    /**
     * @return string[]
     */
    public function getDirectories(): array;
}