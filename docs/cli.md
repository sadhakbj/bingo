# CLI

Bingo includes a CLI powered by Symfony Console. The entry point is `bin/bingo`.

---

## Development Server

```bash
# Start on the default address (http://127.0.0.1:8000)
php bin/bingo serve

# Custom host and port
php bin/bingo serve --host=0.0.0.0 --port=9000
```

Bingo logs all registered routes when the server starts.

---

## Discovery Commands

```bash
# Build or rebuild the discovery cache
php bin/bingo discovery:generate

# Delete the discovery cache
php bin/bingo discovery:clear

# List all registered routes
php bin/bingo show:routes
```

---

## Database Commands

```bash
# Run all pending migrations in database/migrations/
php bin/bingo db:migrate
```

Alias: `db:m`

---

## Code Generators

All generators write files to the appropriate directory under `app/`. Run any generator without arguments to see usage.

### Controller

```bash
php bin/bingo g:controller Users
# → app/Http/Controllers/UsersController.php
```

### Service

```bash
php bin/bingo g:service UserService
# → app/Services/UserService.php
```

### Model

```bash
php bin/bingo g:model Post
# → app/Models/Post.php
```

### Migration

```bash
php bin/bingo g:migration create_posts_table
# → database/migrations/<timestamp>_create_posts_table.php
```

### Middleware

```bash
php bin/bingo g:middleware Auth
# → app/Http/Middleware/AuthMiddleware.php
```

### Exception

```bash
php bin/bingo g:exception PaymentDeclined
php bin/bingo g:exception PaymentDeclined --status=402
# → app/Exceptions/PaymentDeclinedException.php
```

### Command

```bash
php bin/bingo g:command SendDigestEmail
# → app/Console/Commands/SendDigestEmailCommand.php
```

---

## Writing Custom Commands

Generate a stub:

```bash
php bin/bingo g:command ProcessOrders
```

Edit the generated file:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'orders:process', description: 'Process pending orders')]
class ProcessOrdersCommand extends Command
{
    public function __construct(private readonly OrderService $orders)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->orders->processPending();
        $output->writeln("Processed {$count} orders.");
        return Command::SUCCESS;
    }
}
```

Commands that extend Symfony `Command` are discovered automatically from `app/Console/Commands/`. Dependencies are resolved from the container.

Run the command:

```bash
php bin/bingo orders:process
```

---

## Listing All Commands

```bash
php bin/bingo list
```
