<?php

namespace Bramus\Monolog\Formatter;

use Bramus\Monolog\Formatter\ColorSchemes\ColorSchemeInterface;
use \Monolog\Utils;

/**
 * A Colored Line Formatter for Monolog
 */
class ColoredLineFormatter extends \Monolog\Formatter\LineFormatter
{
    /**
     * The Color Scheme to use
     * @var ColorSchemeInterface
     */
    private $colorScheme = null;

    /**
     * Limit stack trace depth
     * @var int
     */
    private $stackLimit = null;

    /**
     * @param string $format                     The format of the message
     * @param string $dateFormat                 The format of the timestamp: one supported by DateTime::format
     * @param bool   $allowInlineLineBreaks      Whether to allow inline line breaks in log entries
     * @param bool   $ignoreEmptyContextAndExtra
     */
    public function __construct($colorScheme = null, $format = null, $dateFormat = null, $allowInlineLineBreaks = false, $ignoreEmptyContextAndExtra = false)
    {
        // Store the Color Scheme
        if ($colorScheme instanceof ColorSchemeInterface) $this->setColorScheme($colorScheme);

        // Call Parent Constructor
        parent::__construct($format, $dateFormat, $allowInlineLineBreaks, $ignoreEmptyContextAndExtra);
    }

    /**
     * Gets The Color Scheme
     * @return ColorSchemeInterface
     */
    public function getColorScheme()
    {
        if (!$this->colorScheme) {
            $this->colorScheme = new ColorSchemes\DefaultScheme();
        }

        return $this->colorScheme;
    }

    /**
     * Sets The Color Scheme
     * @param array
     */
    public function setColorScheme(ColorSchemeInterface $colorScheme)
    {
        $this->colorScheme = $colorScheme;
    }

    /**
     * Sets Stack Trace Limit
     * @param int
     */
    public function setStackLimit(int $stackLimit)
    {
        $this->stackLimit = $stackLimit;
    }

    /**
     * {@inheritdoc}
     */
    public function format(array $record): string
    {
        // Get the Color Scheme
        $colorScheme = $this->getColorScheme();

        // Let the parent class to the formatting, yet wrap it in the color linked to the level
        return $colorScheme->getColorizeString($record['level']) . trim(parent::format($record)) . $colorScheme->getResetString() . "\n";
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
        return $str;
    }

    private function _build_trace_string($frame, $index): string
    {
        $str = "#${index} ";

        $file = $frame['file'];
        if ($file) {
            if (!is_string($file)) {
                $str .= "[unknown function] ";
            } else {
                $line = $frame['line'];
                $str .= $file . ':' . $line . ' ';
            }
        } else {
            $str .= '[internal function] ';
        }
        $str .= $frame['class'];
        $str .= $frame['type'];
        $str .= $frame['function'] . '(';
        $args = $frame['args'];
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
                return substr($arg, 0, 15) . strlen($arg) > 15 ? '...' : '';
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
