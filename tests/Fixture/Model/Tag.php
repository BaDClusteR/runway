<?php

declare(strict_types=1);

namespace Runway\Tests\Fixture\Model;

use Runway\DataStorage\Attribute\Column;
use Runway\DataStorage\Attribute\Id;
use Runway\DataStorage\Attribute\Table;
use Runway\Model\AEntity;

/**
 * A string id (a code): the id must be a query variable, not a part of the SQL.
 *
 * @method string getCode()
 * @method string getLabel()
 * @method static setLabel(string $label)
 */
#[Table('tags')]
class Tag extends AEntity {
    #[Id]
    protected string $code = '';

    #[Column]
    protected string $label = '';
}
