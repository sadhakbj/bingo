# Run Bingo Tests

Run the PHPUnit test suite for the Bingo framework.

## Commands

```bash
# Run all tests
composer test

# Run a specific test file
vendor/bin/phpunit tests/Unit/Core/Data/DataTransferObjectTest.php

# Run a specific test suite
vendor/bin/phpunit --testsuite Unit

# Run with verbose output
vendor/bin/phpunit --verbose

# Run a single test method
vendor/bin/phpunit --filter test_method_name

# Run tests for a specific class/folder
vendor/bin/phpunit tests/Unit/Core/
vendor/bin/phpunit tests/Unit/App/
```

## Test Structure

```
tests/
  Unit/
    Core/
      Data/
        DataTransferObjectTest.php   # DTO fill, toArray, only, except, has, get
        DTOCollectionTest.php        # Collection map, filter, iteration, ArrayAccess
      DTOs/
        Http/
          ApiResponseTest.php        # success, error, notFound, unauthorized, validation
      Http/
        Middleware/
          MiddlewarePipelineTest.php # pipeline chaining, order, short-circuit
      Router/
        RouterTest.php               # route discovery, dispatch, param injection, 404/405
    App/
      DTOs/
        CreateUserDTOTest.php        # Symfony Validator constraints, readonly properties
        UserDTOTest.php              # isAdult, getDisplayName, getMetadata
  Stubs/
    Controllers/
      StubApiController.php          # #[ApiController] stub for router tests
      StubPlainController.php        # plain controller stub
    DTOs/
      SimpleDTOStub.php              # no-constraint DTO for DataTransferObject unit tests
```

## Adding New Tests

1. Place under `tests/Unit/{Core|App}/...` matching the source namespace
2. Namespace: `Tests\Unit\Core\...` or `Tests\Unit\App\...`
3. Extend `PHPUnit\Framework\TestCase`
4. Method names must start with `test_`
5. Add stubs to `tests/Stubs/` if you need concrete classes for abstract/interface testing

## When to Write Tests

- Every new class in `core/` gets a unit test
- Every DTO with validation constraints gets a constraint test
- Every middleware gets a pipeline integration test
- Router tests needed when adding new attribute types
