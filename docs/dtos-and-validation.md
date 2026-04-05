# DTOs and Validation

Bingo provides two mechanisms for validating incoming request data: **`DataTransferObject`** (DTO) for binding and validating a structured payload, and **`ValidatedRequest`** for form-style request objects.

---

## Input DTOs (`DataTransferObject`)

Input DTOs extend `Bingo\Data\DataTransferObject` and declare their fields as public properties. Validation rules are added using [Symfony Validator constraints](https://symfony.com/doc/current/validation.html).

```php
<?php

declare(strict_types=1);

namespace App\DTOs;

use Bingo\Data\DataTransferObject;
use Symfony\Component\Validator\Constraints as Assert;

class CreateUserDTO extends DataTransferObject
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public readonly string $email;

    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 50)]
    public readonly string $name;

    #[Assert\Range(min: 18, max: 120)]
    public readonly ?int $age = null;
}
```

Bind it to a controller parameter with `#[Body]`:

```php
#[Post('/users')]
public function create(#[Body] CreateUserDTO $dto): Response
{
    // Bingo has already filled and validated $dto before this runs.
    // A 422 response is returned automatically if validation fails.
    return Response::json($this->service->create($dto));
}
```

---

## Validation Error Shape

When validation fails, Bingo returns a `422 Unprocessable Content` response:

```json
{
  "statusCode": 422,
  "message": {
    "email": "This value is not a valid email address.",
    "age": "This value should be between 18 and 120."
  },
  "error": "Unprocessable Content"
}
```

---

## DTO Methods

`DataTransferObject` provides several utility methods:

```php
$dto = CreateUserDTO::from(['email' => 'a@b.com', 'name' => 'Alice', 'age' => 30]);

$dto->toArray();             // ['email' => ..., 'name' => ..., 'age' => ...]
$dto->toJson();              // '{"email":...}'
$dto->only(['email']);       // ['email' => '...']
$dto->except(['age']);       // ['email' => '...', 'name' => '...']
$dto->has('email');          // true
$dto->get('email');          // 'a@b.com'
$dto->get('missing', 'X');  // 'X'  (default value)
```

### Creating a DTO from a Model

`fromModel()` fills the DTO from an Eloquent model's `toArray()` output:

```php
$user = User::find($id);
$dto  = UserDTO::fromModel($user);
```

### Nested DTOs

If a public property is typed as another `DataTransferObject` subclass, Bingo will automatically construct it from the nested array:

```php
class OrderDTO extends DataTransferObject
{
    public readonly AddressDTO $shippingAddress;
    public readonly AddressDTO $billingAddress;
}
```

---

## Output DTOs

Output DTOs are plain readonly objects used to shape API responses:

```php
final readonly class UserDTO
{
    public function __construct(
        public int    $id,
        public string $name,
        public string $email,
        public ?int   $age  = null,
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            id:    $user->id,
            name:  $user->name,
            email: $user->email,
            age:   $user->age,
        );
    }
}
```

Return them in a response:

```php
$dto = UserDTO::fromModel($user);
return Response::json(ApiResponse::success($dto)->toArray());
```

---

## `DTOCollection`

`Bingo\Data\DTOCollection` wraps an array of DTOs with collection helpers and implements `ArrayAccess`, `Countable`, and `Iterator`.

```php
use Bingo\Data\DTOCollection;

// Build from an array of raw data
$collection = DTOCollection::make($users->toArray(), UserDTO::class);

// Iterate
foreach ($collection as $user) {
    echo $user->name;
}

// Collection helpers
$collection->count();                          // number of items
$collection->isEmpty();                        // bool
$collection->first();                          // first item or null
$collection->last();                           // last item or null
$collection->map(fn($u) => $u->toArray());    // array
$collection->filter(fn($u) => $u->age > 18);  // new DTOCollection
$collection->toArray();                        // array of toArray() results
$collection->toJson();                         // JSON string

// Array access
$collection[0];                                // first item
```

---

## `ValidatedRequest`

`ValidatedRequest` is an alternative to DTOs for traditional form-style validation. Extend it directly instead of `Bingo\Http\Request`, declare public properties for the expected fields, and annotate them with Symfony constraints.

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Bingo\Validation\ValidatedRequest;
use Symfony\Component\Validator\Constraints as Assert;

class CreatePostRequest extends ValidatedRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 5, max: 200)]
    public string $title;

    #[Assert\NotBlank]
    public string $content;

    #[Assert\Choice(choices: ['draft', 'published'])]
    public string $status = 'draft';
}
```

Use it as a controller parameter via `#[Request]`:

```php
use Bingo\Attributes\Route\Request as ReqAttr;

#[Post('/posts')]
public function store(#[ReqAttr] CreatePostRequest $request): Response
{
    // $request is filled and validated. Validation errors return 422.
    echo $request->title;
    echo $request->status;
}
```

`ValidatedRequest::createFromRequest()` is called automatically by the router; you do not need to call it manually.

---

## Common Symfony Validator Constraints

| Constraint | Example |
|---|---|
| `#[Assert\NotBlank]` | Value must not be empty |
| `#[Assert\Email]` | Must be a valid email address |
| `#[Assert\Length(min: 2, max: 50)]` | String length |
| `#[Assert\Range(min: 0, max: 100)]` | Numeric range |
| `#[Assert\Url]` | Must be a valid URL |
| `#[Assert\Regex(pattern: '/^\d+$/')]` | Must match pattern |
| `#[Assert\Choice(choices: ['a', 'b'])]` | Must be one of the listed values |
| `#[Assert\Count(min: 1, max: 10)]` | Array size |
| `#[Assert\Type(type: 'integer')]` | PHP type check |
| `#[Assert\NotNull]` | Must not be null |

See the [Symfony Validator docs](https://symfony.com/doc/current/validation.html) for the full list.
