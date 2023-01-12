<?php

declare(strict_types=1);

namespace ErrorReporting;

class ErrorException extends \ErrorException
{
    public const ERROR_LEVEL_TO_LABEL = [
        E_WARNING => 'Warning',
        E_NOTICE => 'Notice',
        E_USER_ERROR => 'User Error',
        E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice',
        E_STRICT => 'Runtime Notice',
        E_RECOVERABLE_ERROR => 'Catchable Fatal Error',
        E_DEPRECATED => 'PHP Deprecation Notice',
        E_USER_DEPRECATED => 'Deprecation Notice',
    ];

    public static function fromError(int $severity, string $errorMessage, string $errorFile, int $errorLine): \ErrorException
    {
        return self::createExceptionFromError($severity, $errorMessage, $errorFile, $errorLine);
    }

    public static function fromErrorException(\ErrorException $errorException): \ErrorException
    {
        return self::createExceptionFromError(
            $errorException->getSeverity(),
            $errorException->getMessage(),
            $errorException->getFile(),
            $errorException->getLine()
        );
    }

    private static function createExceptionFromError(int $severity, string $errorMessage, string $errorFile, int $errorLine): \ErrorException
    {
        $exceptionClass = self::determineExceptionClass($severity);
        return self::cleanBacktraceFromErrorHandlerFrames(new $exceptionClass(
            self::ERROR_LEVEL_TO_LABEL[$severity] . ': ' . $errorMessage,
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
