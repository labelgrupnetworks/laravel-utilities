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

## 📄 License

MIT License. See the LICENSE file for details.

## 👥 Authors

- **Eric RF** - erojas@labelgrup.com
- **Manel Alonso** - malonso@labelgrup.com
