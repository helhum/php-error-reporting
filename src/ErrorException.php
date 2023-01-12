<?php

declare(strict_types=1);

namespace ErrorReporting;

class ErrorException extends \ErrorException
{
    public const ERROR_SEVERITY_DESCRIPTION = [
        \E_DEPRECATED => 'Deprecated',
        \E_USER_DEPRECATED => 'User Deprecated',
        \E_NOTICE => 'Notice',
        \E_USER_NOTICE => 'User Notice',
        \E_STRICT => 'Runtime Notice',
        \E_WARNING => 'Warning',
        \E_USER_WARNING => 'User Warning',
        \E_COMPILE_WARNING => 'Compile Warning',
        \E_CORE_WARNING => 'Core Warning',
        \E_USER_ERROR => 'User Error',
        \E_RECOVERABLE_ERROR => 'Catchable Fatal Error',
        \E_COMPILE_ERROR => 'Compile Error',
        \E_PARSE => 'Parse Error',
        \E_ERROR => 'Error',
        \E_CORE_ERROR => 'Core Error',
    ];

    public static function fromError(int $severity, string $errorMessage, string $errorFile, int $errorLine): \ErrorException
    {
        return self::createExceptionFromError($severity, $errorMessage, $errorFile, $errorLine);
    }

    public static function fromErrorException(\ErrorException $errorException): \ErrorException
    {
        if ($errorException instanceof self) {
            return $errorException;
        }
        $message = $errorException->getMessage();
        $severityLabel = self::ERROR_SEVERITY_DESCRIPTION[$errorException->getSeverity()];
        // @todo use str_begins_with once minimum PHP version is > 7
        if (strpos($message, $severityLabel) === 0) {
            // Remove already applied label including colon and space
            $message = substr($message, strlen($severityLabel) + 2);
        }
        return self::createExceptionFromError(
            $errorException->getSeverity(),
            $message,
            $errorException->getFile(),
            $errorException->getLine()
        );
    }

    private static function createExceptionFromError(int $severity, string $errorMessage, string $errorFile, int $errorLine): \ErrorException
    {
        $exceptionClass = self::determineExceptionClass($severity);
        return self::cleanBacktraceFromErrorHandlerFrames(new $exceptionClass(
            self::ERROR_SEVERITY_DESCRIPTION[$severity] . ': ' . $errorMessage,
            1,
            $severity,
            $errorFile,
            $errorLine
        ));
    }

    /**
     * @param int $severity
     * @return class-string<ErrorException>
     */
    private static function determineExceptionClass(int $severity): string
    {
        // @todo convert to match expression once minimum PHP version is 8 or higher
        switch ($severity) {
            case E_ERROR:
            case E_USER_ERROR:
            case E_RECOVERABLE_ERROR:
                return Error::class;
            case E_WARNING:
            case E_USER_WARNING:
                return Warning::class;
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                return DeprecationNotice::class;
            default:
                return self::class;
        }
    }

    private static function cleanBacktraceFromErrorHandlerFrames(\ErrorException $exception): \ErrorException
    {
        $cleanedBacktrace = $backtrace = $exception->getTrace();
        $index = 0;
        while ($index < \count($backtrace)) {
            if (isset($backtrace[$index]['file'], $backtrace[$index]['line']) && $backtrace[$index]['line'] === $exception->getLine() && $backtrace[$index]['file'] === $exception->getFile()) {
                $cleanedBacktrace = \array_slice($backtrace, $index + 1);
                break;
            }
            ++$index;
        }
        $exceptionReflection = new \ReflectionProperty(\Exception::class, 'trace');
        $exceptionReflection->setAccessible(true);
        $exceptionReflection->setValue($exception, $cleanedBacktrace);

        return $exception;
    }
}
