<?php

namespace Tests\Feature;

use App\Logging\RedactOperationalLogs;
use Illuminate\Log\Logger;
use Monolog\Handler\TestHandler;
use Monolog\Logger as Monolog;
use Tests\TestCase;

class OperationalLogRedactionTest extends TestCase
{
    public function test_raw_exceptions_and_untrusted_context_never_reach_operational_handler(): void
    {
        $handler = new TestHandler;
        $logger = new Logger(new Monolog('redaction-test', [$handler]));
        (new RedactOperationalLogs)($logger);
        $logger->error('SQL failed for private@example.test', ['exception' => new \RuntimeException('password=local-secret token=synthetic-token'), 'email' => 'private@example.test', 'password' => 'local-secret', 'request' => ['code' => '123456'], 'status' => 500]);
        $record = $handler->getRecords()[0];
        $json = json_encode($record->toArray());
        foreach (['private@example.test', 'local-secret', 'synthetic-token', '123456'] as $value) {
            $this->assertStringNotContainsString($value, $json);
        }
        $this->assertSame('application.exception', $record->message);
        $this->assertSame(\RuntimeException::class, $record->context['exception_class']);
        $this->assertSame(500, $record->context['status']);
    }
}
