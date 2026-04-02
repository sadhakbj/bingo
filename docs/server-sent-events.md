# Server-Sent Events

Bingo supports Server-Sent Events for one-way streaming responses.

SSE is a good fit for notifications, long-running tasks, streamed text generation, and live progress updates.

## Example

```php
use Bingo\Http\Response;
use Bingo\Http\Sse\StreamedEvent;
use Bingo\Http\StreamedResponse;

#[Get('/notifications/stream')]
public function stream(): StreamedResponse
{
    return Response::eventStream(function (): \Generator {
        yield new StreamedEvent('update', ['status' => 'ok']);
    });
}
```

## Client usage

```javascript
const es = new EventSource('/notifications/stream');

es.addEventListener('update', (e) => {
  console.log(JSON.parse(e.data));
});

es.onmessage = (e) => {
  if (e.data === '</stream>') {
    es.close();
  }
};
```

## Notes

- SSE uses a single HTTP response.
- `EventSource` uses GET requests.
- Custom headers are not available from the browser `EventSource` API.
- Bingo disables buffering for streamed responses.
