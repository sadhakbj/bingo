# Testing

Bingo includes a test-friendly project structure and works with standard PHPUnit workflows.

## Run tests

```bash
composer test
vendor/bin/phpunit --filter ContainerTest
```

## Suggested test layout

```text
tests/
├── Unit/
│   ├── Bingo/
│   └── App/
└── Stubs/
    ├── Controllers/
    └── Services/
```

## Notes

- Keep framework-level tests separate from application tests.
- Use stubs for controller and service behavior that would otherwise require heavy setup.
