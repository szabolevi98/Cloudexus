<?php

namespace Cloudexus\Core;

class Logger
{
    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    private static function write(string $level, string $message, array $context): void
    {
        $line = sprintf(
            '[%s] %s: %s %s%s',
            date('Y-m-d H:i:s'),
            $level,
            $message,
            $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : '',
            PHP_EOL
        );

        $logDir = dirname(__DIR__, 2) . '/var/log';
        $file = $logDir . '/' . date('Y-m-d') . '.log';

        file_put_contents($file, $line, FILE_APPEND);
    }
}
