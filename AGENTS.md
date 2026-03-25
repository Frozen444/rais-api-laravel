# AGENTS.md
Guidance for coding agents working in this Laravel repository.

## Priority order
- Follow explicit user instructions first.
- Follow repository-local conventions next.
- Keep changes minimal, scoped, and reversible.

## Rule files discovered
- `.cursor/rules/`: not present
- `.cursorrules`: not present
- `.github/copilot-instructions.md`: not present
- If any of these files are added later, treat them as high-priority and update this file.

## Stack snapshot
- PHP `^8.3`
- Laravel `^13`
- PHPUnit `^12`
- Laravel Pint `^1.27`
- Vite `^8` + Tailwind CSS `^4`
- Data access is mostly raw SQL via `DB::select`

## Important paths
- `app/Http/Controllers/OaiController.php` - OAI endpoint controller
- `app/Http/Middleware/ValidateOaiRequest.php` - protocol request validation
- `app/Services/OaiDispatcher.php` - OAI verb dispatch
- `app/Services/Handlers/*Handler.php` - per-verb handlers
- `app/Repositories/*Repository.php` - SQL queries + CERIF mapping
- `app/Services/CerifFormatter.php` - shared date/id/normalization helpers
- `app/Exceptions/OaiException.php` - protocol/domain exception type
- `app/Providers/AppServiceProvider.php` - service registrations
- `config/oai.php` - OAI configuration
- `routes/web.php` - `/oai` route

## Setup commands
Preferred one-shot setup:
```bash
composer setup
```
This installs dependencies, bootstraps `.env`, generates app key, runs migrations, installs npm deps, and builds assets.

Manual setup alternative:
```bash
composer install
php -r "file_exists('.env') || copy('.env.example', '.env');"
php artisan key:generate
php artisan migrate
npm install
```

## Build, lint, and test commands

### Run app locally
```bash
composer dev
# Alt (minimal):
php artisan serve
npm run dev
```

### Build/lint/test quick reference
```bash
npm run build
./vendor/bin/pint --test
./vendor/bin/pint
composer test
php artisan test
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
```

### Single test commands (important)
```bash
php artisan test tests/Feature/ExampleTest.php
php artisan test --filter=ExampleTest
php artisan test --filter=test_the_application_returns_a_successful_response
php artisan test tests/Feature/ExampleTest.php --filter=test_the_application_returns_a_successful_response
./vendor/bin/phpunit tests/Feature/ExampleTest.php --filter test_the_application_returns_a_successful_response
```

## Code style conventions

### Formatting
- Follow `.editorconfig`: UTF-8, LF, 4 spaces, trim trailing whitespace (except Markdown).
- Use Laravel Pint defaults for PHP formatting.
- Keep one class per file.
- Prefer trailing commas in multiline arrays for cleaner diffs.

### Imports and namespaces
- `namespace` first, then `use` imports, then class definition.
- Use one import per line.
- Remove unused imports.
- Prefer imported class names over fully-qualified inline class names.

### Typing
- Use typed properties and explicit return types.
- Use nullable types where absence is valid (`?string`, `?array`).
- Keep method parameters strongly typed when possible.
- `declare(strict_types=1);` is not project standard right now; do not introduce broadly unless requested.

### Naming
- Classes: PascalCase (`ListRecordsHandler`, `PublicacionRepository`).
- Methods/properties/variables: camelCase.
- Constants: UPPER_SNAKE_CASE.
- Preserve existing domain language (Spanish DB/domain terms are expected).
- Keep protocol/entity casing exact (`Identify`, `ListRecords`, `Publications`, etc.).

### Architecture and layering
- Keep controllers thin: read request, delegate to services, return response.
- Keep verb-specific behavior in handlers under `app/Services/Handlers`.
- Keep SQL and record mapping in repositories.
- Keep shared normalization/formatting in `CerifFormatter`.
- Register new dependencies in `AppServiceProvider`.

### SQL and data access
- Use parameterized SQL (`?` placeholders + bound params).
- Never concatenate untrusted values directly into SQL.
- Follow existing pattern: query with `DB::select`, then map rows/arrays.
- Keep multiline SQL readable for joins and complex filters.

### OAI/CERIF response contract
- Preserve OAI envelope keys: `OAI-PMH`, `responseDate`, `request`, and verb payload.
- Preserve JSON XML-like conventions: `@...` for attributes, `#text` for node text.
- Keep current metadata prefix support (`perucris-cerif`) unless explicitly expanded.
- Keep OAI identifier and CERIF id shapes consistent with `CerifFormatter` helpers.
- Use `config('oai.*')` and env-backed values over hardcoded deployment data.

### Error handling
- Validate required OAI params early (middleware and handler guard clauses).
- Throw `App\Exceptions\OaiException` for protocol/domain validation failures.
- Convert exceptions to OAI-compliant error payloads in controller responses.
- Avoid leaking internals in production (debug detail only when debug mode is enabled).
- Catch broad `\Throwable` only when partial aggregation is intentional.

### Tests
- Put endpoint contract tests in `tests/Feature`.
- Put pure helper/formatter tests in `tests/Unit`.
- Name tests by behavior, not implementation details.
- Keep fixtures small and assertion-focused.

## Change checklist for agents
- Run `./vendor/bin/pint --test` after PHP edits.
- Run targeted tests first, broader suite second.
- For API changes, verify JSON keys and date/identifier formats.
- Avoid unrelated refactors in the same patch.
- Update this file when scripts, tooling, or agent rule files change.
