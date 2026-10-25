# Changelog

## 2.0.0

The changes since 1.0.3. The upgrade steps are in [Breaking changes](#breaking-changes).

### Breaking changes

- **Routes are path templates.** `/chats/{chatId}/chunks/{index<\d+>}`: the placeholder values (URL-decoded) are passed
  to the controller method by their names. A placeholder is any text without slashes by default (the
  `route_wildcard_regexp` parameter); the other regexps are set inline or in `requirements`. `Router::process()` takes
  the method too.
- **The database connection is a DSN:** `DSN`, `DB_USER`, `DB_PASSWORD`, `DB_PREFIX` instead of `DB_HOST`, `DB_PORT`,
  `DB_NAME`, `DB_ENCODING` (`DBConnectOptionsDTO` is replaced by `DataStorageConnectionOptionsDTO`). The driver gives
  its options (`IDataStorageDriver::getConnectOptions()`) and has `connect()`.
- **`setLimit($limit, $offset)` makes `LIMIT $limit OFFSET $offset`.** It made `LIMIT $limit, $offset`, which MySQL reads
  as the offset first: a call that passed the offset first (`setLimit($offset, $count)`) must swap the arguments.
- `IQueryBuilder::getResult()` is `getResults()`; `from()` takes an entity class too. The interface has the new methods
  (see Added): an implementation of it must have them.
- `select()` takes strings or expressions (`string|Stringable`).
- `count()` counts all the matching rows: the limit and the order of the query are ignored.
- `RequestParameterValue::asInt()` / `asString()` of an array (`?id[]=1`) are `0` / `""` (they were `1` / `"Array"` with
  a warning).
- The files of a field with several files (`photos[]`) are separate fields: `photos[0]`, `photos[1]` (nested ones too).
- The details of an exception or an error (the message, the file, the line) are shown only with `APP_DEBUG=true`.
- `X-Forwarded-For` is used for the client IP address only if the request came from one of `TRUSTED_PROXIES`.
- The default log level is info (`LOG_LEVEL=1`).
- `Request::getHeader()` / `getCookie()` give `"0"` (it was `null`); a cookie that is an array is `null`.
- `PDO` dispatches the error events with the event dispatcher of its constructor (not the one of the container).
- `bootstrap.php` defines the constants only: the config directory and the `.env` file of the package are the defaults
  of `PathsProvider` (no class is loaded when the autoloader is included).
- The package has no `index.php` (a front controller of the times when Runway was an application): the project has its
  own one.
- The configs do not declare the services of the classes that do not exist (`Runway\Module\IModuleProvider`,
  `Runway\IRequestParameterValue`, `IQueryOperationConverter`, `Runway\Model\Converter\IDataStoragePropertyBuilder`).

### Security

- **SQL injection:** the id of a model in `UPDATE` (`persist()`) and `DELETE` (`remove()`) was a part of the SQL: a
  string id (e.g. a code) could change the query. It's a query variable now.
- The values of `IN` in `AEntity::find()` are query variables (they were quoted, but not escaped).
- The errors and exceptions do not show the messages, paths and SQL without `APP_DEBUG=true`; they are logged without
  the arguments of the calls.
- `X-Forwarded-For` cannot be forged: it's trusted only from `TRUSTED_PROXIES`.
- The dumper (`dump()`, `dd()`) escapes HTML in the browser.
- A parse error of a `.env` file does not contain its content (the secrets).
- A module of `MODULES` is a folder name only: `../other` is skipped.
- The array request values are not converted to `1` / `"Array"` (type juggling of `?id[]=`).
- The error handler does not log the errors suppressed with `@`.

### Added

- **Router:** `methods` (`405 Method Not Allowed` with the `Allow` header, `OPTIONS` gets the allowed methods, `HEAD` is
  allowed wherever `GET` is), `requirements`, `priority`, the most specific route wins (a static segment beats a
  placeholder), conflicting routes are an error, route providers (the services with the `router.route_provider` tag),
  `Request::getRouteMatch()`, `IMethodNotAllowedController`.
- **Streamed responses:** `StreamedResponse` sends the body by a callback as it's made (e.g. a big file assembled on the
  fly), without `Content-Length` unless it's given.
- `CookieDTO` has `SameSite` (`Lax` by default).
- **Query builder:** `getEntities()`, `iterateEntities()`, `getFirstEntity()`, `setEntityFQN()`, `from(Entity::class)`,
  `into($table, $columns)`, `columns()`, `addColumn()`, `addColumns()`;
  `INSERT ... SET` is made portable (`(columns) VALUES (values)`); the driver of the builder can be given to its
  constructor.
- **Models:** arrays are conditions of `find()` (`IN`), `null` is `IS NULL`; `iterate()`; `persist()` saves the referenced
  entities first and the loaded references after the entity.
- `Request::getFiles()`, `FileDTO::getContent()`, `FileSystem::remove()` (`CannotDeleteFileException`).
- `IContainer::getService()` / `tryGetService()` are generic: the service of an interface is its instance for the static
  analysis.
- A tag of a service can be just its name (`tags: [app.tag]`).
- PHPUnit tests (unit, integration, E2E), PHPStan at the max level, Infection (MSI 93%), CI on Linux, macOS and Windows
  with PHP 8.4 and 8.5.

### Fixed

- **Query builder:** `LIKE` / `NOT LIKE` were `IS LIKE` (not SQL); `IS NULL` / `IS NOT NULL` can be in `AND` / `OR` and
  `isNotNull()` in `where()`; `count()` of a page was `0` from the second page; `SELECT` without columns is `SELECT *`;
  `ORDER BY` direction in any case (`desc`); `DELETE` was broken; constructing a builder does not connect to the
  database.
- **Models:** two new entities that refer to each other are an error, not an endless recursion (the guard of `persist()`
  was never set); a change made by a setter of the entity itself is saved (only the magic setters were noticed); a
  `#[NoGetter]` prop is saved (it was `NULL`); `persist()` does not load the references from the database just to save
  them; no reference search of a non-persistent entity; an int column given by the driver as a string, a float
  (`DECIMAL`) column; the entity is stored before its references; the prop types conversion.
- **Container:** a service that cannot be created does not break the next requests of it (the dependency chain was left,
  `tryGetService()` then reported a circular dependency); a decorator is applied once; a union type parameter is not a
  fatal error; a class that does not exist is `ClassNotFoundException`.
- **Config:** a route controller without `::method`, a route that is not a map, a tag without a name are configuration
  errors (they were warnings and type errors); a missing config directory.
- **Request:** a malformed request URI is the root path; the body of a request without one; the headers and the
  arguments that are not strings.
- **Environment:** a missing or non-string `MODULES`, a numeric `DB_PASSWORD`, a missing `DB_PREFIX` were type errors;
  an empty module name made the path `/config`.
- `Converter`: `capitalize('')`, a malformed date in `dateTimeToTimestamp()` (a type error, now `-1`).
- `Dumper`: the `<pre>` of the CLI and the browser were swapped, `print()` passed the variables to `print_r()` as its
  options, `dump()` without arguments.
- `FileSystem`: the message of `CannotReadFileException`, warnings instead of the exceptions, the check of an existing
  destination.
- `ModuleProvider`: the modules directory of `PathsProvider`, a missing modules directory.
- `Logger`: the malformed UTF-8 of the context, the Unicode is not escaped.
- Event listeners: a protected method of a listener is skipped, not a fatal error; a controller method that is not public
  is `RouteMethodNotFound`.

### Changed

- `PathsProvider::getModulesDirectory()` is `MODULE_ROOT` unless another directory is set.
- The SQL state code is passed to `PDOException`.
- `composer.lock` is not tracked (a library); the `version` field is not in `composer.json` (the tags are the versions).
