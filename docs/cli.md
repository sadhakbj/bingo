# CLI

Bingo includes a CLI powered by Symfony Console.

## Start the development server

```bash
php bin/bingo serve
php bin/bingo serve --host=0.0.0.0 --port=9000
```

## Discovery commands

```bash
php bin/bingo discovery:generate
php bin/bingo discovery:clear
php bin/bingo show:routes
```

## Database migrations

```bash
php bin/bingo db:migrate
```

## Generators

- `g:controller`
- `g:service`
- `g:middleware`
- `g:exception`
- `g:model`
- `g:migration`
- `g:command`

## Custom commands

Commands can be registered in `bootstrap/console.php` and receive dependencies through the container.
