<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\LogRecord;
use Throwable;

class RedactOperationalLogs
{
    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(function (LogRecord $record): LogRecord {
            $safe = [];
            foreach (['status', 'duration_ms', 'attempt'] as $key) {
                if (isset($record->context[$key]) && is_numeric($record->context[$key])) {
                    $safe[$key] = (int) $record->context[$key];
                }
            }
            foreach (['request_id', 'key_id', 'channel_id', 'outbox_id'] as $key) {
                $value = $record->context[$key] ?? null;
                if (is_string($value) && preg_match('/^[a-f0-9-]{32,36}$/Di', $value)) {
                    $safe[$key] = $value;
                }
            }
            $events = ['external_summary_api', 'external_summary_api_error', 'Notification channel test failed.', 'Notification delivery failed.', 'UniversalOpenAiVisionAdapter request failed', 'UniversalOpenAiVisionAdapter exception'];
            $message = in_array($record->message, $events, true) ? $record->message : 'application.event';
            $exception = $record->context['exception'] ?? null;
            if ($exception instanceof Throwable) {
                $message = 'application.exception';
                $safe['exception_class'] = get_class($exception);
                $safe['source_file'] = basename($exception->getFile());
                $safe['source_line'] = $exception->getLine();
            } elseif (isset($record->context['exception_type']) && is_string($record->context['exception_type']) && preg_match('/^[A-Za-z_\\\\][A-Za-z0-9_\\\\]*$/D', $record->context['exception_type'])) {
                $safe['exception_class'] = $record->context['exception_type'];
            }

            return $record->with(message: $message, context: $safe, extra: []);
        });
    }
}
