<?php

declare(strict_types=1);

namespace Runway\Tests\Fixture\Model;

use Runway\DataStorage\Attribute\Column;
use Runway\DataStorage\Attribute\Id;
use Runway\DataStorage\Attribute\Table;
use Runway\Model\AEntity;

/**
 * A reference to an entity of the same model: two new nodes that refer to each other cannot be saved.
 *
 * @method int|null getId()
 * @method static setName(string $name)
 * @method Node|null getPartner()
 * @method static setPartner(Node|null $partner)
 */
#[Table('nodes')]
class Node extends AEntity {
    #[Id]
    protected ?int $id = null;

    #[Column]
    protected string $name = '';

    #[Column]
    protected ?Node $partner = null;
}
