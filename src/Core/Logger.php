<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Log de aplicacao com timestamp: logs/app.log
 */
final class Logger
{
    private static string $file = '';

    public static function init(string $file): void
    {
        self::$file = $file;
    }

    public static function info(string $msg, array $ctx = []): void  { self::write('INFO', $msg, $ctx); }
    public static function warning(string $msg, array $ctx = []): void { self::write('WARNING', $msg, $ctx); }
    public static function error(string $msg, array $ctx = []): void { self::write('ERROR', $msg, $ctx); }

    private static function write(string $level, string $msg, array $ctx): void
    {
        if (self::$file === '') {
            return;
        }
        $line = sprintf(
            "[%s] %s: %s %s | ip=%s\n",
            date('Y-m-d H:i:s'),
            $level,
            $msg,
            $ctx ? json_encode($ctx, JSON_UNESCAPED_UNICODE) : '',
            $_SERVER['REMOTE_ADDR'] ?? 'cli'
        );
        @file_put_contents(self::$file, $line, FILE_APPEND | LOCK_EX);
    }
}
