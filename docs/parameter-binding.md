# Parameter Binding

Controller method parameters are resolved automatically from the incoming request. Each binding attribute maps to a different part of the request.

---

## Quick Reference

```php
public function handle(
    #[Body]                       CreateUserDTO $dto,
    #[Param('id')]                int $id,
    #[Query('page')]              int $page = 1,
    #[Query('q')]                 ?string $search = null,
    #[Headers('x-api-version')]   ?string $version = null,
    #[Request]                    Request $request,
    #[UploadedFile('avatar')]     ?UploadedFile $file = null,
    #[UploadedFiles]              array $files = [],
): Response
```

---

## `#[Body]`

Binds the request body to a DTO. Bingo fills the DTO from the parsed request body and validates it before the action is called.

```php
use Bingo\Attributes\Route\Body;

#[Post('/users')]
public function create(#[Body] CreateUserDTO $dto): Response
{
    // $dto is already validated — if validation fails, a 422 is returned automatically
    return Response::json($this->service->create($dto));
}
```

Supported content types: `application/json`, `application/x-www-form-urlencoded`, `multipart/form-data`.

See [DTOs and Validation](dtos-and-validation.md) for how to define input DTOs.

---

## `#[Param]`

Extracts a named segment from the route path. The value is automatically cast to the declared PHP type.

```php
use Bingo\Attributes\Route\Param;

#[Get('/users/{id}')]
public function show(#[Param('id')] int $id): Response { /* … */ }

#[Get('/posts/{slug}')]
public function showPost(#[Param('slug')] string $slug): Response { /* … */ }
```

---

## `#[Query]`

Reads a query string parameter. Provide a default value to make the parameter optional.

```php
use Bingo\Attributes\Route\Query;

#[Get('/users')]
public function index(
    #[Query('page')]   int     $page  = 1,
    #[Query('limit')]  int     $limit = 20,
    #[Query('q')]      ?string $search = null,
    #[Query('sort')]   string  $sort  = 'created_at',
): Response { /* … */ }
```

---

## `#[Headers]`

Reads a request header by name (case-insensitive).

```php
use Bingo\Attributes\Route\Headers;

#[Get('/data')]
public function data(
    #[Headers('x-api-version')]  ?string $version = null,
    #[Headers('x-tenant-id')]    ?string $tenantId = null,
): Response { /* … */ }
```

---

## `#[Request]`

Injects the raw `Bingo\Http\Request` object (a Symfony `Request` subclass).

```php
use Bingo\Attributes\Route\Request as ReqAttr;
use Bingo\Http\Request;

#[Post('/webhook')]
public function webhook(#[ReqAttr] Request $request): Response
{
    $signature = $request->headers->get('X-Webhook-Signature');
    $body      = $request->getContent();
    // …
}
```

---

## `#[UploadedFile]`

Retrieves a single named file upload.

```php
use Bingo\Attributes\Route\UploadedFile;
use Symfony\Component\HttpFoundation\File\UploadedFile as File;

#[Post('/users/{id}/avatar')]
public function uploadAvatar(
    #[Param('id')]           int   $id,
    #[UploadedFile('avatar')] ?File $avatar = null,
): Response {
    if ($avatar && $avatar->isValid()) {
        $path = $avatar->move(base_path('storage/uploads'), $avatar->getClientOriginalName());
    }
    return Response::json(['uploaded' => $avatar !== null]);
}
```

---

## `#[UploadedFiles]`

Injects all uploaded files as an array keyed by field name.

```php
use Bingo\Attributes\Route\UploadedFiles;

#[Post('/upload')]
public function uploadMany(#[UploadedFiles] array $files = []): Response
{
    $saved = [];
    foreach ($files as $fieldName => $file) {
        if ($file->isValid()) {
            $saved[] = $file->getClientOriginalName();
        }
    }
    return Response::json(['saved' => $saved]);
}
```

---

## Type Casting

`#[Param]` and `#[Query]` values arrive as strings from the HTTP layer. Bingo casts them to the declared PHP type automatically.

| Declared type | Source string | Result |
|---|---|---|
| `int` | `"42"` | `42` |
| `float` | `"3.14"` | `3.14` |
| `bool` | `"true"` | `true` |
| `bool` | `"1"` | `true` |
| `string` | `"hello"` | `"hello"` |

Nullable types (`?int`, `?string`) receive `null` when the parameter is absent and no default is provided.

---

## Combining Multiple Bindings

All binding attributes can appear on the same method:

```php
#[Post('/teams/{teamId}/members')]
public function addMember(
    #[Param('teamId')]          int     $teamId,
    #[Body]                     AddMemberDTO $dto,
    #[Query('notify')]          bool    $notify = false,
    #[Headers('x-actor-id')]    ?string $actorId = null,
    #[ReqAttr]                  Request $request,
): Response { /* … */ }
```
