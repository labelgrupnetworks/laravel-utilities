# Laravel Utilities 🛠️ <img src="https://img.shields.io/badge/repository-public-green.svg?logo=laravel" alt="Public Repository Badge" />

A comprehensive collection of utilities to improve and streamline Laravel applications. This package provides Artisan commands for scaffolding API-ready components, reusable helper classes, custom exception handling, validation rules, and support for modern API development patterns.

## ✅ Requirements
- PHP `^8.0`
- Laravel `^9.2|^10.0|^11.0|^12.0|^13.0`
- PHP Extensions: `zip`, `curl`

## 📦 Installation
```bash
composer require labelgrup/laravel-utilities
```

The package is automatically registered through Laravel's package discovery.

## 📚 Table of Contents
- [Commands](#-commands)
  - [MakeApiRequest](#-makeapirequest)
  - [MakeUseCase](#-makeusecase)
- [Core Classes](#-core-classes)
  - [ApiRequest](#apirequest)
  - [UseCase & UseCaseInterface](#usecase--usecaseinterface)
  - [UseCaseResponse](#usecaseresponse)
- [CustomException](#-customexception)
- [Helpers](#-helpers)
  - [ApiResponse](#apiresponse)
  - [ExceptionHandler](#exceptionhandler)
  - [Image](#image)
  - [Password](#password)
  - [Text](#text)
  - [Time](#time)
  - [Zip](#zip)
- [Validation Rules](#-validation-rules)
  - [SlugRule](#slugrule)
- [MCP Tools (`AI\Mcp`)](#-mcp-tools-aimcp)
  - [Tool bases: ControllerTool & UseCaseTool](#tool-bases-controllertool--usecasetool)
  - [Response & error mapping](#response--error-mapping)
  - [Scope authorization](#scope-authorization)
  - [Input/output schemas](#inputoutput-schemas)
  - [Configuration](#configuration)
  - [Claude Code skills installer](#claude-code-skills-installer)

---

## ⚙️ Commands

### 📝 MakeApiRequest

Generates an API request class in **`app/Http/Requests/Api/`** with built-in JSON validation error handling.

```bash
php artisan make:api-request {ApiRequestName}
```

The generated class extends `ApiRequest` and provides automatic JSON response formatting for validation failures. It's designed to work seamlessly with API endpoints.

**Example Usage:**
```php
namespace App\Http\Requests\Api;

use Labelgrup\LaravelUtilities\Core\Requests\ApiRequest;

class CreateUserRequest extends ApiRequest
{
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8'
        ];
    }
}
```

### 🧠 MakeUseCase

Generates a **Use Case** class in **`app/UseCases/`** to encapsulate and organize business logic into reusable, testable actions.

```bash
php artisan make:use-case {UseCaseName} {--without-validation}
```

The generated class extends `UseCase`, implements `UseCaseInterface`, and includes built-in exception handling and response management.

**Example with Validation:**
```php
namespace App\UseCases;

use Labelgrup\LaravelUtilities\Core\UseCases\UseCase;
use Labelgrup\LaravelUtilities\Core\UseCases\WithValidateInterface;

class CreateUserUseCase extends UseCase implements WithValidateInterface
{
    public function __construct(
        private UserRepository $userRepository
    ) {}

    public function action()
    {
        // Your business logic here
        return $this->userRepository->create($this->getData());
    }

    public function validate(): void
    {
        $validator = validator($this->getData(), [
            'name' => 'required|string',
            'email' => 'required|email|unique:users'
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
```

**Customizing Response:**
```php
public string $response_message = 'User created successfully';
public int $success_status_code = 201; // HTTP_CREATED

// Or override the handle() method for complete control
public function handle(): UseCaseResponse
{
    // Custom logic here
    return parent::handle();
}
```

---

## 🏗️ Core Classes

### ApiRequest

Located in `src/Core/Requests/ApiRequest.php`, this abstract class extends Laravel's `FormRequest` and automatically formats validation errors as JSON responses, making it ideal for API controllers.

**Features:**
- Automatic JSON error responses for validation failures
- Uses `ApiResponse::fail()` for consistent error formatting
- Returns HTTP 422 (Unprocessable Entity) on validation error

### UseCase & UseCaseInterface

Located in `src/Core/UseCases/`, these classes provide a pattern for organizing business logic.

**UseCase Class:**
- Abstract base class with built-in exception handling
- Calls `validate()` automatically if the `WithValidateInterface` is implemented
- Wraps errors in `UseCaseResponse` with proper HTTP status codes
- Configurable `success_status_code` and `response_message`

**UseCaseInterface:**
Requires implementation of the `action()` method, which contains your business logic.

### UseCaseResponse

A standardized response object returned by all Use Cases containing:
- `success` (bool) - Whether the operation succeeded
- `message` (string) - Response message
- `http_code` (int) - HTTP status code
- `data` (mixed) - Response payload
- `error_code` (string|null) - Custom error code for logging/debugging
- `trace` (array) - Exception trace (only on failures)

---

## ❗ CustomException

Provides a custom exception class that extends `Exception` and implements Laravel's `Renderable` interface for fine-grained control over API error responses.

```php
public function __construct(
    public string $error_code,
    public string $error_message,
    public int $http_code = Response::HTTP_INTERNAL_SERVER_ERROR,
    public ?array $report_data = ['logs' => []],
    public ?array $response = [],
    public bool $should_render = true
)
```

**Parameters:**
- `error_code`: Unique identifier for the exception (e.g., `'USER_NOT_FOUND'`)
- `error_message`: Human-readable error message
- `http_code`: HTTP status code (defaults to 500)
- `report_data`: Additional context for logging
- `response`: Custom response data
- `should_render`: Whether to render the exception

**Example Usage:**
```php
throw new CustomException(
    error_code: 'USER_NOT_FOUND',
    error_message: 'The requested user does not exist',
    http_code: Response::HTTP_NOT_FOUND
);
```

**Automatic Logging:**
The exception automatically logs request information including method, parameters, and user context when reported.

---

## 🔧 Helpers

### ApiResponse

Provides fluent methods for consistent API response formatting.

**Available Methods:**

**`ok(data, code = 200, streamJson = false): JsonResponse|StreamedJsonResponse`**
Returns a success response with data only.
```php
return ApiResponse::ok(['users' => $users]);
```

**`done(message, data = [], code = 200, streamJson = false): JsonResponse|StreamedJsonResponse`**
Returns a success response with message and optional data.
```php
return ApiResponse::done('Users retrieved successfully', ['users' => $users]);
```

**`fail(message, data = [], code = 400, error_code = null, trace = []): JsonResponse|StreamedJsonResponse`**
Returns a failure response with error information.
```php
return ApiResponse::fail('Validation failed', $errors, Response::HTTP_UNPROCESSABLE_ENTITY);
```

**`stream(data): StreamedJsonResponse`**
Returns a streamed JSON response for large datasets.
```php
return ApiResponse::stream($largeDataset);
```

### ExceptionHandler

Provides a centralized exception rendering system for both legacy (Laravel <11) and current Laravel versions.

**Static `render()` Method:**
Handles rendering of various exception types including:
- `CustomException` - Custom error responses
- `ValidationException` - Validation error responses  
- `AuthenticationException` - Authentication failures (401)
- `UnauthorizedException` - Authorization failures (401)
- `NotFoundHttpException` - Resource not found (404)
- `HttpException` - Generic HTTP exceptions

**Usage in Exception Handler:**
```php
public function render($request, Throwable $exception)
{
    return ExceptionHandler::render($exception, $request);
}
```

### Image

Provides image manipulation utilities.

**`getExtensionImageFromUrl(url): ?string`**
Extracts the file extension from an image URL.
```php
$ext = Image::getExtensionImageFromUrl('https://example.com/image.jpg'); // 'jpg'
```

**`destroy(src): bool`**
Deletes an image from storage.
```php
Image::destroy('uploads/profile.jpg'); // true/false
```

**`downloadFromUrl(url, fileName): void`**
Downloads an image from a URL and saves it locally. Uses cURL with proper headers and timeouts.
```php
Image::downloadFromUrl('https://example.com/avatar.png', 'local_avatar.png');
```

### Password

Provides password generation and validation with security checks.

**`rule(min_size = 12): PasswordRule`**
Returns a validated password rule with requirements:
- Minimum length (default 12 characters)
- Mixed case letters
- Numbers
- Symbols
- Must not be in known leaked password databases

```php
'password' => [
    'required',
    'confirmed',
    Password::rule(16) // Minimum 16 characters
]
```

**`generateSecurePassword(length = 12, max_retries = 5): string`**
Generates a cryptographically secure random password that is not in breach databases.

Attempts up to `max_retries` times to generate a password not found in leaked password databases.

```php
$password = Password::generateSecurePassword(16); // length: 16
```

### Text

String manipulation utilities with UTF-8 support.

**`sanitize(text, divider = '-'): string`**
Sanitizes text for URL-safe slugs:
- Removes non-alphanumeric characters
- Converts to ASCII transliteration
- Lowercases the result
- Removes duplicate separators
- Returns 'n-a' for empty results

```php
Text::sanitize('Héllo Wørld!'); // 'hello-world'
Text::sanitize('User Profile', '_'); // 'user_profile'
```

### Time

Converts seconds to human-readable time format.

**`parseTimeForHumans(seconds, unitMin = 's'): string`**
Breaks down seconds into readable time units (weeks, days, hours, minutes, seconds).

- `unitMin` parameter controls the minimum unit to display:
  - `'s'` - seconds (default)
  - `'i'` - minutes  
  - `'h'` - hours
  - `'d'` - days
  - `'w'` - weeks

```php
Time::parseTimeForHumans(3661); // '1 hour 1 minute 1 second'
Time::parseTimeForHumans(86400, 'h'); // '1 day'
Time::parseTimeForHumans(604800, 'd'); // '1 week'
```

### Zip

File compression utilities.

**`create(zipFile, sourcePath): ?string`**
Creates a ZIP archive from a directory recursively.

Returns the path to the created ZIP file or null on failure.

```php
Zip::create(
    storage_path('exports/archive.zip'),
    storage_path('files')
);
```

The method:
- Preserves directory structure
- Includes all files recursively
- Returns the file path on success

---

## 🎯 Validation Rules

### SlugRule

Validates that a string conforms to valid slug format (`[a-z0-9]+(?:-[a-z0-9]+)*`).

A slug must:
- Contain only lowercase letters and numbers
- Allow hyphens as separators
- Start and end with alphanumeric characters

**Usage:**
```php
use Labelgrup\LaravelUtilities\Rules\SlugRule;

'slug' => ['required', 'string', new SlugRule()]

// Valid: product-name, user-profile-123
// Invalid: Product-Name, user_profile, -invalid-
```

---

## 🤖 MCP Tools (`AI\Mcp`)

Framework for building [`laravel/mcp`](https://github.com/laravel/mcp) tools that reuse an app's existing controllers or use cases instead of re-implementing business logic. Requires `laravel/mcp` (listed under `suggest`, not `require` — install it yourself if you use this namespace; the package auto-detects its presence, see [Configuration](#configuration)).

```
Labelgrup\LaravelUtilities\AI\Mcp\
├─ Schemas/
│  ├─ Attributes/{Schema, OutputSchema}          ← declare a Tool's input/output schema by class reference
│  ├─ Interfaces/{SchemaInterface, OutputSchemaInterface}
│  └─ Traits/PaginationTrait                     ← generic paginated-output schema (integers only)
└─ Tools/
   ├─ Abstracts/{ControllerTool, UseCaseTool}    ← the two Tool bases
   ├─ DTO/{EndpointDTO, UseCaseDTO}
   ├─ Errors/DefaultToolErrorResolver
   ├─ Interfaces/{ControllerToolInterface, UseCaseToolInterface, ToolErrorResolverInterface,
   │  ToolErrorResponseBuilderInterface, McpScopeAuthorizerInterface}
   └─ Resolvers/{ResolvesToolResponse, ResolvesToolSchemas}
```

### Tool bases: ControllerTool & UseCaseTool

**`ControllerTool`** reuses an existing API controller method. The Tool declares an `EndpointDTO` (controller class, method, optional `FormRequest` class, plus scalar route `params`/Eloquent `models` for methods with route-model-binding). The base builds and validates that `FormRequest` from the MCP arguments, calls the controller, and maps the resulting `JsonResponse`:

```php
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Abstracts\ControllerTool;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\DTO\EndpointDTO;

class SearchProductTool extends ControllerTool
{
    public function endpoint(): EndpointDTO
    {
        return new EndpointDTO(SearchEngineController::class, 'searchProducts', SearchProductRequest::class);
    }

    public function schema(JsonSchema $schema): array
    {
        return [ /* input schema */ ];
    }
}
```

`request` is nullable (no-FormRequest methods are called with no arguments). The controller's own validation, guards and permission checks apply for free — the Tool never bypasses them.

**`UseCaseTool`** calls a use case directly, without an HTTP controller in between. The Tool declares a `UseCaseDTO` (the use case instance + `responseToApi()` flags):

```php
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Abstracts\UseCaseTool;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\DTO\UseCaseDTO;

class SearchProductTool extends UseCaseTool
{
    public function useCase(Request $request): UseCaseDTO
    {
        $validated = $request->validate((new SearchProductRequest)->rules());

        return new UseCaseDTO(use_case: new SearchProductsUseCase($validated), response_simplified: true);
    }

    public function schema(JsonSchema $schema): array
    {
        return [ /* input schema */ ];
    }
}
```

`useCase()` runs inside the response wrapper, so its own validation/construction errors are mapped cleanly. Note that `handle()`'s default flow calls `$use_case->handle()->responseToApi(...)` — the use case's own business exceptions are swallowed into a `JsonResponse` by `UseCase::handle()` before ever reaching the error resolver below. A Tool that wants a business exception to reach the error resolver raw must override `handle()` and call `perform()`/`action()` directly instead.

A consumer needing a third input shape (e.g. `Spatie\LaravelData\Data` objects) implements its own sibling of these two — see NAP's `DataObjectControllerTool` for a worked example — rather than this package trying to cover every possible input shape.

### Response & error mapping

Both bases use the `ResolvesToolResponse` trait, funneling every call through `respond(callable)`:

- `authorizeScope()` runs first — throws `UnauthorizedException` if the Tool isn't `#[IsReadOnly]` and the caller lacks write access (see [Scope authorization](#scope-authorization));
- the callable's return is normalised: `string` → text, `array` → structured (via the overridable `transformResponse()` hook), `JsonResponse` → mapped by HTTP status (`responseFromJsonResponse()`), anything else → `'Success'`;
- any `Throwable` → `report($e)` + `errorResponse($e)`.

`responseFromJsonResponse()` maps by status: `5xx` → generic internal error; `4xx` → `error` (`error`/`message` from the body, sanitized); `204`/empty (with no output schema declared) → `'Success'`; otherwise → the structured body.

`errorResponse()` delegates to a configurable resolver (`config('laravel-utilities.mcp.errors.resolver')`, default `DefaultToolErrorResolver` — must implement `ToolErrorResolverInterface`). `DefaultToolErrorResolver::resolve()`:

1. `ValidationException` → flattened field errors.
2. `HttpResponseException` → unwraps the underlying `JsonResponse`.
3. Otherwise, if the exception's class is listed in `config('laravel-utilities.mcp.errors.exposed_exceptions')` → delegates to the app's own `ExceptionHandler::render()` (same mapping as the REST API).
4. Otherwise, if `app.debug` is `true` → the raw (sanitized) exception message.
5. Otherwise → generic `'Tool is temporarily unavailable'` (the real error only reaches the log, via `report()`).

A consumer can add business exceptions to `exposed_exceptions` with zero code changes, or swap `errors.resolver` entirely for a project-specific mapping without forking this package.

### Scope authorization

`shouldRegister()`/`authorizeScope()` gate writes: a Tool without `#[IsReadOnly]` requires write access; read-only tools are always listed and callable. Write access itself is resolved via `config('laravel-utilities.mcp.scope_authorizer')` — a class implementing `McpScopeAuthorizerInterface::canWrite(): bool`, bound in the consuming app's own container/config. This package has no opinion on *how* a consumer determines write access (token scopes, roles, anything else) — it only defines the interface and the resolution point. If unset, `canWrite()` defaults to unrestricted (`true`).

### Input/output schemas

`ResolvesToolSchemas` provides default `schema()`/`outputSchema()` implementations for Tools that declare `#[Schema(SomeClass::class)]` / `#[OutputSchema(SomeClass::class, key: '...', many: false, scalar: false)]` instead of hand-writing the array inline. `#[Schema]` is repeatable (merged in declaration order) for input combining multiple shape sources. A Tool can still override either method directly — an explicit override always wins over the attribute.

`PaginationTrait::paginationOutputSchema()` provides a generic paginated-output shape (`items` + `data.{current_page,last_page,total_items}`, all plain integers). A consumer whose pagination metadata needs a richer numeric type (e.g. formatted/localized numbers) writes its own sibling trait instead of extending this one — see NAP's `PaginationGenesisTrait` for a worked example.

### Configuration

Publish the config with `php artisan vendor:publish --tag=laravel-utilities-config` (or copy `config/laravel-utilities.php` from the package). The `mcp` block:

```php
'mcp' => [
    // Auto-detects laravel/mcp; only pays the cost of the rate limiter/config validation when true.
    'enabled' => env('LARAVEL_UTILITIES_MCP_ENABLED', class_exists(\Laravel\Mcp\Server::class)),

    // Must implement McpScopeAuthorizerInterface. Null = unrestricted writes.
    'scope_authorizer' => null,

    'errors' => [
        'resolver' => \Labelgrup\LaravelUtilities\AI\Mcp\Tools\Errors\DefaultToolErrorResolver::class,
        'exposed_exceptions' => [
            // FQCNs whose ->getMessage() is safe to expose to the MCP client.
        ],
    ],

    'rate_limit' => [
        'per_minute' => env('MCP_RATE_LIMIT_PER_MINUTE', 60),
        'whitelist_ips' => [],
    ],
],
```

When `mcp.enabled` is true, `LaravelUtilitiesServiceProvider` registers a named `mcp` rate limiter (`RateLimiter::for('mcp', ...)`, throwing `CustomException` with `AI-MCP-RATELIMIT-0001` on `HTTP_TOO_MANY_REQUESTS`) and validates the config — a consumer only needs to apply `throttle:mcp` to its own MCP route(s).

### Claude Code skills installer

The package ships two Claude Code skills under `skills/Mcp/` (`mcp-tool-builder`, `mcp-tool-reviewer`) to help build/review Tools against the conventions above. Publishing them is opt-in — deliberately not a Composer Plugin, so it never runs (or requires trust-gate approval) on every consumer's `composer install`/`update`, only when a project actually wants them.

When `mcp.enabled` is true, `LaravelUtilitiesServiceProvider::publishSkills()` registers each `skills/Mcp/<name>` for publishing under the `laravel-utilities-skills` tag:

```bash
php artisan vendor:publish --tag=laravel-utilities-skills
```

This copies each skill into the consuming project's `.claude/skills/<name>`. Like any Laravel `publishes()` call, existing files at the target are left untouched unless `--force` is passed.

---

## 📄 License

MIT License. See the LICENSE file for details.

## 👥 Authors

- **Eric RF** - erojas@labelgrup.com
- **Manel Alonso** - malonso@labelgrup.com
