<?php

declare(strict_types=1);

namespace Runway\Tests\Fixture\Model;

use DateTime;
use Runway\DataStorage\Attribute\Column;
use Runway\DataStorage\Attribute\Id;
use Runway\DataStorage\Attribute\NoGetter;
use Runway\DataStorage\Attribute\NoSetter;
use Runway\DataStorage\Attribute\Reference;
use Runway\DataStorage\Attribute\Table;
use Runway\Model\AEntity;

/**
 * The props of every type: int, string, DateTime and bool (int columns), array (a JSON string), float, a custom
 * column name, no getter, no setter, the references.
 *
 * @method int|null getId()
 * @method string getName()
 * @method static setName(string $name)
 * @method DateTime|null getCreatedAt()
 * @method static setCreatedAt(DateTime|int|string|null $createdAt)
 * @method bool getIsActive()
 * @method static setIsActive(bool $isActive)
 * @method array<mixed> getTags()
 * @method static setTags(array<mixed> $tags)
 * @method float|null getScore()
 * @method static setScore(float|null $score)
 * @method string getNickname()
 * @method static setNickname(string $nickname)
 * @method static setPassword(string $password)
 * @method Post[] getPosts()
 */
#[Table('users')]
class User extends AEntity {
    #[Id]
    protected ?int $id = null;

    #[Column]
    protected string $name = '';

    #[Column]
    protected ?DateTime $createdAt = null;

    #[Column]
    protected bool $isActive = false;

    /**
     * @var array<mixed>
     */
    #[Column]
    protected array $tags = [];

    #[Column]
    protected ?float $score = null;

    #[Column(name: 'nick')]
    #[NoSetter]
    protected string $nickname = '';

    #[Column]
    #[NoGetter]
    protected string $password = '';

    /**
     * @var Post[]|null
     */
    #[Reference(refModel: Post::class, refProp: 'author', refOrderBy: ['title', 'DESC'])]
    protected ?array $posts = null;

    protected string $notStored = 'not stored';

    /**
     * @param array<mixed> $tags
     */
    protected function toArrayTags(array $tags): string {
        return implode(',', array_map(static fn(mixed $tag): string => is_scalar($tag) ? (string)$tag : '', $tags));
    }

    public function getPasswordForTest(): string {
        return $this->password;
    }

    public function setNicknameForTest(string $nickname): void {
        $this->nickname = $nickname;
    }
}
