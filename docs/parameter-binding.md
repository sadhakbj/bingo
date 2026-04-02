# Parameter Binding

Controller parameters are resolved automatically from the current request.

## Supported bindings

```php
public function handle(
    #[Body]                     CreateUserDTO $dto,
    #[Param('id')]              int $id,
    #[Query('page')]            int $page = 1,
    #[Query('q')]               ?string $search = null,
    #[Headers('x-api-version')] ?string $version = null,
    #[Request]                  Request $request,
    #[UploadedFile('avatar')]   ?UploadedFile $file = null,
    #[UploadedFiles]            array $files = [],
): Response
```

## Available attributes

- `#[Body]` for JSON or form payloads mapped into DTOs
- `#[Param]` for route parameters
- `#[Query]` for query string values
- `#[Headers]` for request headers
- `#[Request]` for the raw Symfony request object
- `#[UploadedFile]` for a single file upload
- `#[UploadedFiles]` for all uploaded files

## Type conversion

`#[Param]` and `#[Query]` values are cast to the declared PHP type when possible.

Supported scalar types include:

- `int`
- `float`
- `bool`
- `string`

Missing non-nullable query parameters fall back to their default values when one is defined.
