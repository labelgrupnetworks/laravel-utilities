# Laravel Utilities 🛠️ <img src="https://img.shields.io/badge/repository-public-green.svg?logo=laravel" alt="Public Repository Badge" />

A collection of utilities to improve and streamline Laravel projects.

## ✅ Requirements
- PHP `^8.1`
- Laravel `^10.43`

## 📦 Installation
```bash
composer require labelgrup/laravel-utilities
```

## 📚 Table of Contents
- [Commands](#️-commands)
  - [MakeApiRequest](#-makeapirequest)
  - [MakeUseCase](#-makeusecase)
- [CustomException](#️-customexception)
- [Helpers](#️-helpers)
  - [ApiResponse](#apiresponse)
  - [ExceptionHandler](#exceptionhandler)
  - [Image](#image)
  - [Password](#password)
  - [Text](#text)
  - [Time](#time)
  - [Zip](#zip)
- [Rules](#️-rules)
  - [SlugRule](#slugrule)

---

## ⚙️ Commands

### 📝 MakeApiRequest

Creates a Request tailored for API usage in **`/App/Http/Requests/Api/`**. Handles request parsing and JSON-based responses/failures.

> Behaves like native Laravel Form Requests.

```bash
php artisan make:api-request {ApiRequestName}
```

### 🧠 MakeUseCase

Generates a **UseCase** class in **`/App/UseCases/`** to decouple and organize business logic into strong actions.

```bash
php artisan make:use-case {UseCaseName} {--without-validation}
```

The generated class extends `UseCase` and implements `UseCaseInterface`. You'll need to define the `action()` method and optionally `validate()` for input validation.

```php
use Labelgrup\LaravelUtilities\Core\UseCases\UseCase;
use Labelgrup\LaravelUtilities\Core\UseCases\WithValidateInterface;

class ExampleUseCase extends UseCase implements WithValidateInterface
{
    public function action()
    {
        // Implement your use case here
    }

    public function validate(): void
    {
        // Implement validation logic here
    }
}
```

To customize the response, override the `handle()` method and return a `UseCaseResponse` object.

```php
public string $response_message = 'Action has been finished';
public int $success_status_code = 201;
```

---

## ❗ CustomException

Provides a custom exception handler that extends `Exception` and implements Laravel’s `Renderable` interface for better control over API error responses.

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

### Properties
- `error_code`: Identifier for the exception.
- `error_message`: Message returned in the response.
- `http_code`: HTTP status code.
- `report_data`: Additional info for logs.
- `response`: Custom response body.
- `should_render`: Whether the exception should be rendered or not.

---

## 🧰 Helpers

### 📤 ApiResponse

Simplifies API response formatting:

```php
ApiResponse::done(string $message, array $data = [], int $code = 200, bool $streamJson = false);
ApiResponse::fail(string $message, array $errors = [], int $code = 400, bool $streamJson = false);
ApiResponse::response(array $data, int $code, bool $streamJson = false);
```

---

### 🧯 ExceptionHandler

Centralized error rendering.

```php
public static function render(\Throwable $exception, Request $request);
```

#### Laravel 10.x
In `app/Exceptions/Handler.php`:

```php
ExceptionHandler::render($e, $request);
```

#### Laravel 11.x
In `bootstrap/app.php`:

```php
->withExceptions(function (Exceptions $exceptions) {
    ExceptionHandler::render($exceptions);
})
```

---

### 🖼️ Image

Image utilities:

```php
Image::getExtensionImageFromUrl(string $url): ?string;
Image::destroy(string $src): bool;
Image::downloadFromUrl(string $url, string $fileName): void;
```

---

### 🔐 Password

Password utilities:

```php
Password::rule(int $min_size): PasswordRule;
Password::generateSecurePassword(int $length = 12): string;
Password::isLeakedPassword(string $password): bool;
```

---

### ✍️ Text

Text sanitization:

```php
Text::sanitize(string $text, string $divider = '-'): string;
```

---

### ⏳ Time

Human-readable time conversion:

```php
Time::parseTimeForHumans(int $inputSeconds, string $unitMin = 's'): string;
```

---

### 🗜️ Zip

Zipping utilities:

```php
Zip::create(string $zipFile, string $sourcePath): ?string;
```

---

## 🧾 Rules

### 🐌 SlugRule

Get a rule for validating slugs in `RequestForm`.
