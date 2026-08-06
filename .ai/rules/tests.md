---
paths:
    - 'tests/**'
---

# Tests

## expectsOutput* misses console-component output

Commands that print via the console components (`$this->components->twoColumnDetail`, e.g. `channel:list`, `route:list`) write straight to the output stream, which `PendingCommand`'s mocked output does not record. `->expectsOutputToContain(...)` therefore fails even when the text is plainly there.

Read the buffer instead:

```php
$this->withoutMockingConsoleOutput();
expect($this->artisan('channel:list'))->toBe(0)
    ->and(Artisan::output())->toContain('documents.{document}');
```
