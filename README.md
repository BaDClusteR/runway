# Runway

A Symfony-like platform to build websites and web apps: a service container configured in YAML, a router, requests
and responses, models on PDO with a query builder, a logger, ENV variables and modules.

```shell
composer require bad_cluster/runway:^2.0
```

PHP 8.4+ with the `dom`, `fileinfo`, `openssl`, `pdo`, `simplexml`, `yaml` and `zip` extensions.

## Useful tips

1. Define `PROJECT_ROOT` before calling `Kernel::processRequest`: the logs are written to the "log" subfolder there
   (into the package folder otherwise).
2. Add the config directories and the `.env` files of the project with `PathsProvider::getInstance()`
   (`addConfigDirectory()`, `addEnvFilePath()`) before the first service is requested: the configs are read once.

## Services

The YAML files of the config directories (and of the `config` folders of the enabled modules) are merged:

```yaml
parameters:
  greeting: 'Hello'

services:
  App\IGreeter:
    class: App\Greeter
    arguments:
      greeting: '%greeting'      # a parameter
      logLevel: '#LOG_LEVEL'     # an ENV variable
      logger: '@Runway\Logger\ILogger'   # a service
    tags:
      - name: app.greeter
        priority: 10
      - app.other_tag            # a tag without options

  App\PoliteGreeter:
    decorates: App\IGreeter      # the constructor gets the decorated service by its type (or '@inner')

  App\Listener:
    events:
      - event: kernel.init
        method: onInit
```

- The other constructor parameters are the services of their types (a nullable one is null, a default value wins).
- A service that extends `Runway\Singleton` is its only instance.
- `Container::getServicesByTag()` gives the tagged services by the priority (the lower first).
- A circular dependency (with the chain of the calls), a missing class, an untyped parameter or a duplicate service
  is an exception; the errors of a config file name the file.

## Routes

```yaml
routes:
  chat_messages:
    path: /chats/{chatId<\d+>}/messages/{messageId}
    controller: App\Controller\Chat::messages   # a service or a class
    methods: [GET, PATCH]                         # any method by default
    requirements: { messageId: '\d+' }
    priority: 0
```

- The placeholder values (URL-decoded) are passed to the controller method by their names; the method returns a
  `Response` (or a `StreamedResponse`, whose body is sent by a callback as it is made).
- A placeholder is any text without slashes by default (the `route_wildcard_regexp` parameter); `{path<.+>}` can take
  several segments. The value is not checked otherwise: a controller of a file path must check it (e.g. `..`).
- If several routes match, the one with the highest priority wins, then the most specific one (a static segment beats
  a placeholder). If the path matches, but the method does not, the response is `405` with the `Allow` header;
  `OPTIONS` gets the allowed methods. `HEAD` is allowed wherever `GET` is.
- Other route sources are the services with the `router.route_provider` tag (`IRouteProvider`).
- The details of an exception or an error (the message, the file) are shown only with `APP_DEBUG=true`, they are
  logged anyway.

## Requests

`IRequestRead` has the query, form and server parameters (`getGetParameter('id')->asInt()`; an array is `0` / `""`, not
`"Array"`), the headers, the cookies, the body and the uploaded files (a `photos[]` field is split into `photos[0]`,
`photos[1]`, ...). The IP address is `REMOTE_ADDR`: `X-Forwarded-For` is used only if the request came from one of
`TRUSTED_PROXIES` (comma-separated).

## Models

```php
#[Table('users')]
class User extends AEntity {
    #[Id]
    protected ?int $id = null;

    #[Column]
    protected string $name = '';

    #[Column]                      // created_at (int)
    protected ?DateTime $createdAt = null;

    #[Column]                      // author_id
    protected ?User $author = null;

    #[Column]
    #[NoGetter]                    // saved, but not in toArray() and get*()
    protected string $passwordHash = '';

    #[Reference(refModel: Post::class, refProp: 'author', refOrderBy: ['title', 'DESC'])]
    protected ?array $posts = null;
}

$user = new User(5);                                       // EntityNotFoundException if there is none
$users = User::find(['name' => ['Ann', 'Bob'], 'createdAt' => null], ['name', 'DESC'], [10, 20]);
$user->setName('Ann')->persist();                          // INSERT or UPDATE (if changed)
```

- The conditions of `find()` are props (`null` is `IS NULL`, an array is `IN`), the values are query variables. The
  order is a prop (an unknown one is ignored), the limit is `limit` or `[limit, offset]`.
- `persist()` saves the referenced entities first and the loaded references after the entity.
- The query builder (`AEntity::getQueryBuilder()`, `IQueryBuilder`) makes the SQL of the expressions as is: the values
  must be query variables (`->where($qb->expr()->eq('name', ':name'))->setVariable('name', $name)`), not concatenated.
  `` `{table}` `` gets the `DB_PREFIX`.
- The connection: `DSN`, `DB_USER`, `DB_PASSWORD`, `DB_PREFIX`. The driver connects on the first query.

## Upgrading to 2.0

See [CHANGELOG.md](CHANGELOG.md) for all the changes. In short:

- The routes are path templates (`/chats/{chatId<\d+>}`), the placeholders are passed to the controller by their names.
- The database connection is `DSN` (with `DB_USER`, `DB_PASSWORD`, `DB_PREFIX`) instead of `DB_HOST`, `DB_NAME`, etc.
- `IQueryBuilder::setLimit($limit, $offset)` makes `LIMIT $limit OFFSET $offset`: it was `LIMIT $limit, $offset`, which
  MySQL reads as the offset first. A call that passed the offset first (`setLimit($offset, $count)`) must swap them.
- `LIKE` / `NOT LIKE` comparisons are valid SQL (they were `IS LIKE`).
- `count()` ignores the limit and the order of the query.
- `IDataStorageDriver` has `connect()` (the container called it anyway); `IQueryBuilder` has `into($table, $columns)`,
  `columns()`, `addColumn()`, `addColumns()`; `select()` takes strings or expressions (`Stringable`).
- `RequestParameterValue::asInt()` / `asString()` of an array are `0` / `""`.
- The `QueryBuilder` constructor takes an optional driver and connects on the first query, not when it is created.
- `PDO` dispatches the error events with its own event dispatcher (the constructor one).

## Development

```shell
composer test               # PHPUnit: the unit, integration and E2E tests
composer test:unit          # the classes one by one, the data storage is a fake driver
composer test:integration   # the container and the configs in the process, SQLite in memory
composer test:e2e           # HTTP requests to the PHP built-in server
composer phpstan            # PHPStan, the max level
composer infection          # mutation testing of the unit tests (pcov or Xdebug)
```

The tests need the `pdo_sqlite` extension. The CI runs them on Linux, macOS and Windows with PHP 8.4 and 8.5.
