<?php

namespace Bramus\Monolog\Formatter;

use Bramus\Monolog\Formatter\ColorSchemes\DefaultScheme;
use Bramus\Monolog\Formatter\ColorSchemes\ColorSchemeInterface;
use \Monolog\Utils;
use Monolog\LogRecord;

/**
 * A Colored Line Formatter for Monolog
 */
class ColoredLineFormatter extends \Monolog\Formatter\LineFormatter
{
    /**
     * The Color Scheme to use
     * @var ColorSchemeInterface
     */
    private ColorSchemeInterface $colorScheme;

    /**
     * Limit stack trace depth
    */
    private ?int $stackLimit = null;

    /**
     * @param ColorSchemeInterface|null $colorScheme
     * @param string|null $format                The format of the message
     * @param string|null $dateFormat            The format of the timestamp: one supported by DateTime::format
     * @param bool        $allowInlineLineBreaks Whether to allow inline line breaks in log entries
     * @param bool $ignoreEmptyContextAndExtra
     */
    public function __construct(?ColorSchemeInterface $colorScheme = null, ?string $format = null, ?string $dateFormat = null, bool $allowInlineLineBreaks = false, bool $ignoreEmptyContextAndExtra = false)
    {
        // Store the Color Scheme
        if (!$colorScheme) {
            $this->colorScheme = new DefaultScheme();
        } else {
            $this->colorScheme = $colorScheme;
        }

        // Call Parent Constructor
        parent::__construct($format, $dateFormat, $allowInlineLineBreaks, $ignoreEmptyContextAndExtra);
    }

    /**
     * Gets The Color Scheme
     * @return ColorSchemeInterface
     */
    public function getColorScheme(): ColorSchemeInterface
    {
        return $this->colorScheme;
    }

    /**
     * Sets The Color Scheme
     * @param ColorSchemeInterface $colorScheme
     */
    public function setColorScheme(ColorSchemeInterface $colorScheme)
    {
        $this->colorScheme = $colorScheme;
    }

    /**
     * Sets Stack Trace Limit
     */
    public function setStackLimit(int $stackLimit)
    {
        $this->stackLimit = $stackLimit;
    }

    /**
     * {@inheritdoc}
     */
    public function format(LogRecord $record) : string
    {
        // Get the Color Scheme
        $colorScheme = $this->getColorScheme();

        // Let the parent class to the formatting, yet wrap it in the color linked to the level
        return $colorScheme->getColorizeString($record->level->value) . trim(parent::format($record)) . $colorScheme->getResetString() . "\n";
    }

    protected function normalizeException(\Throwable $e, int $depth = 0): string
    {
        $previousText = '';
        if ($previous = $e->getPrevious()) {
            do {
                $previousText .= ', ' . Utils::getClass($previous) . '(code: ' . $previous->getCode() . '): ' . $previous->getMessage() . ' at ' . $previous->getFile() . ':' . $previous->getLine();
            } while ($previous = $previous->getPrevious());
        }

        $str = $this->formatException($e);

        return $str;
    }

    private function formatException(\Throwable $e): string
    {
        $str = '[object] (' . Utils::getClass($e) . '(code: ' . $e->getCode();
        if ($e instanceof \SoapFault) {
            if (isset($e->faultcode)) {
                $str .= ' faultcode: ' . $e->faultcode;
            }
            if (isset($e->faultactor)) {
                $str .= ' faultactor: ' . $e->faultactor;
            }
            if (isset($e->detail)) {
                $str .= ' detail: ' . $e->detail;
            }
        }
        $str .= '): ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine() . ')';
        if ($this->includeStacktraces) {
            $str .= "\n[stacktrace]\n" . $this->getTraceAsString($e) . "\n";
        }
        return $str;
    }

    public function getTraceAsString(\Throwable $e): string
    {
        $str = '';
        $trace = $e->getTrace();
        if (!is_array($trace)) {
            return $str;
        }
        foreach ($trace as $index => $frame) {
            if ($this->stackLimit && $index >= $this->stackLimit) {
                break;
            }
            if (!is_array($frame)) {
                continue;
            }
            $str .= $this->_build_trace_string($frame, $index);
        }
        return substr($str, 0, -2);
    }

    private function _build_trace_string($frame, $index): string
    {
        $str = "#{$index} ";

        if (isset($frame['file'])) {
            $file = $frame['file'];
            if (!is_string($file)) {
                $str .= "[unknown function] ";
            } else {
                $line = $frame['line'];
                $str .= $file . ':' . $line . ' ';
            }
        } else {
            $str .= '[internal function] ';
        }
        $str .= $frame['class'] ?? '';
        $str .= $frame['type'] ?? '';
        $str .= $frame['function'] ?? '';
        $str .= '(';
        $args = $frame['args'] ?? false;
        if (is_array($args)) {
            $last_arg = count($args) - 1;
            foreach ($args as $key => $value) {
                $str .= $this->_build_trace_args($value);
                if ($key != $last_arg) {
                    $str .= ', ';
                }
            }
        }
        $str .= ")\n";
        return $str;
    }

    private function _build_trace_args($arg): string
    {
        switch (gettype($arg)) {
            case "boolean":
                return $arg ? 'true' : 'false';
            case "integer":
                return $arg;
            case "double":
                return sprintf("%.2f", $arg);
            case "string":
                return "'" . substr($arg, 0, 15) . (strlen($arg) > 15 ? '...' : '') . "'";
            case "object":
                return 'Object(' . get_class($arg) . ')';
            case "array":
            case "resource":
            case "resource (closed)":
            case "NULL":
                return gettype($arg);
        }
        return "unknown type";
    }
}
