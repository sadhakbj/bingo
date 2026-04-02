# DTOs and Validation

Bingo uses DTOs to represent request input and structured output.

## Input DTOs

Input DTOs extend `Bingo\Data\DataTransferObject` and define validation rules using Symfony Validator attributes.

```php
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

When a DTO is bound through `#[Body]`, Bingo fills it from the request and validates it automatically.

Validation failure returns a 422 response before the controller action is called.

## Output DTOs

Output DTOs can be plain readonly objects.

```php
final readonly class UserDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?int $age = null,
    ) {}
}
```

## API response wrapper

`ApiResponse` can be used to return a consistent JSON envelope.

```php
return Response::json(
    ApiResponse::success(data: UserDTO::fromModel($user), statusCode: 201)->toArray(),
    201,
);
```

## Validation error shape

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
