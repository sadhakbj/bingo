# Server-Sent Events

Bingo has built-in support for Server-Sent Events (SSE) and raw chunked streaming through `Response::eventStream()` and `Response::stream()`.

SSE is a good fit for live notifications, progress updates, streaming AI responses, and any scenario where the server needs to push data to the browser over a long-lived HTTP connection.

---

## Basic SSE Example

```php
use Bingo\Http\Response;
use Bingo\Http\Sse\StreamedEvent;
use Bingo\Http\StreamedResponse;

#[Get('/notifications/stream')]
public function stream(): StreamedResponse
{
    return Response::eventStream(function (): \Generator {
        yield new StreamedEvent('update', ['status' => 'processing', 'progress' => 10]);

        sleep(1);

        yield new StreamedEvent('update', ['status' => 'processing', 'progress' => 50]);

        sleep(1);

        yield new StreamedEvent('done', ['status' => 'complete', 'progress' => 100]);
    });
}
```

The framework automatically sends an end-of-stream event after the generator is exhausted. Buffering is disabled automatically for the response.

---

## `StreamedEvent`

| Argument | Type | Description |
|---|---|---|
| `$event` | `string\|null` | SSE event name (`null` for the default `message` event) |
| `$data` | `mixed` | Data to send; arrays and objects are JSON-encoded automatically |

```php
// Named event
yield new StreamedEvent('user.created', ['id' => 42, 'name' => 'Alice']);

// Default "message" event (no event name)
yield new StreamedEvent(null, 'plain text payload');

// Object data
yield new StreamedEvent('tick', new \stdClass());
```

---

## Custom End-of-Stream Event

By default, Bingo sends `data: </stream>\n\n` as the terminator. Override it:

```php
return Response::eventStream(
    producer: function (): \Generator {
        yield new StreamedEvent('update', ['n' => 1]);
    },
    endStreamWith: new StreamedEvent('end', ['finished' => true]),
);
```

---

## Yielding Raw Values

The producer callable can yield non-`StreamedEvent` values. Strings, arrays, and objects are encoded automatically:

```php
return Response::eventStream(function (): \Generator {
    yield ['count' => 1];   // becomes: data: {"count":1}
    yield 'plain text';      // becomes: data: plain text
});
```

---

## Client-Side Usage

```javascript
const es = new EventSource('/notifications/stream');

es.addEventListener('update', (e) => {
    const payload = JSON.parse(e.data);
    console.log('Progress:', payload.progress);
});

es.addEventListener('done', (e) => {
    console.log('Stream complete');
    es.close();
});

// Catch the default end-of-stream marker
es.onmessage = (e) => {
    if (e.data === '</stream>') {
        es.close();
    }
};

es.onerror = (e) => {
    console.error('SSE error', e);
    es.close();
};
```

### Browser Limitations

- `EventSource` always uses GET requests.
- Custom request headers cannot be set from the browser `EventSource` API. Use query parameters or cookies for authentication.
- For POST-based SSE, use `fetch()` with a `ReadableStream` on the client side.

---

## Raw Chunked Streaming

For non-SSE chunked responses (arbitrary content type), use `Response::stream()`:

```php
use Bingo\Http\Response;

#[Get('/export/csv')]
public function exportCsv(): \Bingo\Http\StreamedResponse
{
    return Response::stream(function (): void {
        echo "id,name,email\n";
        flush();

        foreach (User::cursor() as $user) {
            echo "{$user->id},{$user->name},{$user->email}\n";
            flush();
        }
    }, 200, ['Content-Type' => 'text/csv']);
}
```

`Response::stream()` applies `X-Accel-Buffering: no` automatically. Additional headers can be passed in the third argument.

---

## Notes

- Bingo disables output buffering for all streamed responses.
- `CompressionMiddleware` skips SSE and stream responses automatically.
- SSE connections hold an HTTP connection open. Configure your load balancer and reverse proxy to support long-lived connections (increase idle timeouts in nginx / ALB).
- For horizontal scaling with SSE, consider publishing events through Redis pub/sub and subscribing in the generator.
