<?php

declare(strict_types=1);

namespace Runway\Tests\Fixture\Model;

use Runway\DataStorage\Attribute\Column;
use Runway\DataStorage\Attribute\Id;
use Runway\DataStorage\Attribute\Table;
use Runway\Model\AEntity;

/**
 * A reference to another entity (the "author_id" column) and an explicit getter and setter.
 *
 * @method int|null getId()
 * @method User|null getAuthor()
 * @method static setAuthor(User|null $author)
 */
#[Table('posts')]
class Post extends AEntity {
    #[Id]
    protected ?int $id = null;

    #[Column]
    protected string $title = '';

    #[Column]
    protected ?User $author = null;

    public function getTitle(): string {
        return $this->title;
    }

    /**
     * The setter of the entity: the title without the spaces around.
     */
    public function setTitle(string $title): static {
        $this->title = trim($title);

        return $this;
    }
}
