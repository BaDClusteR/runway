<?php

declare(strict_types=1);

namespace Runway\Tests\Fixture\Model;

use Runway\DataStorage\Attribute\Column;
use Runway\DataStorage\Attribute\Table;
use Runway\Model\AEntity;

#[Table('no_primary')]
class NoPrimaryProp extends AEntity {
    #[Column]
    protected string $name = '';
}
