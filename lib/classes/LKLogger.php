<?php

class LKLoggerOptions {
    /** @var string Directory where the log files will be created */
    public $logDir;
    /** @var int Maximum number of log lines to store in the memory buffer. The older lines will be removed when the limit is reached */
    public $memoryBufferLines = 10000;
    /** @var boolean If true, the information about the function and line that generated the log will be displayed in the message */
    public $showStack = false;
    /** @var boolean If true, the log message will be preceded by as many tabs as the stack depth of the function that generated the log */
    public $tabStackDepth = false;
    /**
     * Custom function to generate logs.
     * The prototype of the function must be:
     * function ($logLevel, $log, $tabLevel = 0)<br>
     * Where:
     * <ul>
     * <li>$logLevel: contains the log level assigned to the log message</li>
     * <li>$log: log message</li>
     * <li>$tabLevel: Addtional tabs to append at the begining of the log message</li>
     * </ul>
     *
     * @var callable
     */
    public $customFunction = null;
}

class LKLogInfo {
    public $date;
    public $level;
    public $stackDepth;
    public $stackLine;
    public $stackFunction;
    public $message;
}

class LKLogger {
    /* Log level constants */
    const LEVEL_DEBUG = 'debug';
    const LEVEL_TRACE = 'trace';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_NONE = 'none';

    /* Log output type constants */
    /** @var string Generate logs in a memory buffer. Intended for storing the logs of temporary processes. */
    const OUTPUT_MEMORY = 1;
    /** @var string Generate logs in a file. It is necessary to provide the directory where the logs will be generated */
    const OUTPUT_FILE = 2;
    /** @var string Generate logs in the standard output (console) */
    const OUTPUT_CONSOLE = 4;
    /** @var string Send logs to the OS Syslog */
    const OUTPUT_SYSLOG = 8;
    /** @var string Send logs to a custom function */
    const OUTPUT_CUSTOM = 16;

    /* Private mebers */
    private $logLevel;
    private $outputType;
    private $addStackInfo = false;
    private $options;
    private $logDirAvailable = null;
    /** @var LKLogInfo[] Only for memory logs*/
    private $logBuffer = [];

    /**
     *
     * @param string $logLevel One of the following log levels: debug, trace, info, warning, error
     * @param int $outputType Integer composed as a bit mask by the combination of the following options: memory=1, file=2, console=3, syslog=4
     * @param LKLoggerOptions $options
     */
    public function __construct($logLevel, $outputType, $options = null) {
        $this->options = $options ?? new LKLoggerOptions();

        if (!$logLevel) {
            $logLevel = self::LEVEL_ERROR;
        }

        $logLevel = strtolower($logLevel);
        $this->logLevel = self::getLevelOrder($logLevel);
        $this->outputType = $outputType;
    }

    /**
     * Prepares the log file in the directory provided.
     * If the log directory cannot be created or doesn't have 'write' permission, then the generation of logs to file cannot be used.
     *
     * @return boolean Returns <i>true</i> if the initialization was successful or <i>false</i> otherwise
     */
    private function initLogFile() {
        if ($this->logDirAvailable !== null) {
            // Already initialized
            return $this->logDirAvailable;
        }

        $logDir = $this->options->logDir;
        $success = false;
        if ($logDir) {
            $logDir = rtrim($logDir, '/') . '/';
            // If a log directory has been provided, verify that it exists and try to create it otherwise
            if (!is_dir($logDir)) {
                mkdir($logDir);
            }

            if (!is_dir($logDir)) {
                error_log('Service logger initialization error: cannot access directory ' . $logDir);
            } else {
                // Check write permission
                $testFileName = $logDir . "testWrite.log";
                file_put_contents($testFileName, "", FILE_APPEND);
                if (file_exists($testFileName)) {
                    $success = true;
                    unlink($testFileName);
                } else {
                    error_log('Service logger initialization error: missing write permission in directory ' . $logDir . ' or disk full');
                }
            }
        }

        $this->options->logDir = $logDir;
        $this->logDirAvailable = $success;
        if (!$success) {
            // Deactivate logging to file
            $this->outputType = $this->outputType & ~self::OUTPUT_FILE;
        }
    }

    /**
     * Generate a trace of DEBUG level
     *
     * @param string $log
     * @param int $tabulation
     */
    public function debug($log, $tabulation = 0) {
        if ($this->logLevel > self::getLevelOrder(self::LEVEL_DEBUG)) {
            return;
        }

        $this->log(self::LEVEL_DEBUG, $log, $tabulation);
    }

    /**
     * Generate a trace of TRACE level
     *
     * @param string $log
     * @param int $tabulation
     */
    public function trace($log, $tabulation = 0) {
        if ($this->logLevel > self::getLevelOrder(self::LEVEL_TRACE)) {
            return;
        }

        $this->log(self::LEVEL_TRACE, $log, $tabulation);
    }

    /**
     * Generate a trace of INFO level
     *
     * @param string $log
     * @param int $tabulation
     */
    public function info($log, $tabulation = 0) {
        if ($this->logLevel > self::getLevelOrder(self::LEVEL_INFO)) {
            return;
        }

        $this->log(self::LEVEL_INFO, $log, $tabulation);
    }

    /**
     * Generate a trace of WARNING level
     *
     * @param string $log
     * @param int $tabulation
     */
    public function warning($log, $tabulation = 0) {
        if ($this->logLevel > self::getLevelOrder(self::LEVEL_WARNING)) {
            return;
        }

        $this->log(self::LEVEL_WARNING, $log, $tabulation);
    }

    /**
     * Generate a trace of ERROR level
     *
     * @param string $log
     * @param int $tabulation
     */
    public function error($log, $tabulation = 0) {
        if ($this->logLevel > self::getLevelOrder(self::LEVEL_ERROR)) {
            return;
        }

        $this->log(self::LEVEL_ERROR, $log, $tabulation);
    }

    /**
     * Returns an array of all logs generated in a memory buffer
     *
     * @return LKLogInfo[]
     */
    public function getLogs() {
        return $this->logBuffer;
    }

    /**
     * Generate a trace of ERROR level
     *
     * @param string $log
     * @param int $tabulation
     */
    static private function getLevelOrder($logLevel) {
        switch ($logLevel) {
            case self::LEVEL_DEBUG :
                return 1;
            case self::LEVEL_TRACE :
                return 2;
            case self::LEVEL_INFO :
                return 3;
            case self::LEVEL_WARNING :
                return 4;
            case self::LEVEL_ERROR :
                return 5;
            case self::LEVEL_NONE :
            default :
                return 1000;
        }
    }

    /**
     * Create a log trace
     *
     * @param string $logLevel
     * @param string $log
     * @param number $tabLevel
     */
    private function log($logLevel, $log, $tabLevel = 0) {
        $now = new DateTime('now', new DateTimeZone('UTC'));
        $datetime = $now->format('Y-m-d H:i:s');
        $date = explode(' ', $datetime)[0];

        $logInfo = new LKLogInfo();
        $logInfo->date = $datetime;
        $logInfo->level = $logLevel;

        $line = 0;
        if ($this->options->showStack || $this->options->tabStackDepth) {
            $stackDepth = 0;
            $stackTrace = debug_backtrace();
            if (count($stackTrace) <= 2) {
                $function = 'main';
            } else {
                $function = $stackTrace[2]['function'];
                if ($stackTrace[2]['class']) {
                    // If is a member of a class, add the class name
                    $function = $stackTrace[2]['class'] . '::' . $function;
                }

                $line = $stackTrace[2]['line'];
                $stackDepth = max(count($stackTrace) - 3, 0);
            }

            $logInfo->stackDepth = $stackDepth;
            $logInfo->stackLine = $line;
            $logInfo->stackFunction = $function;
        }

        $logInfo->message = $log;

        /* Log to memory buffer */
        if ($this->outputType & self::OUTPUT_MEMORY) {
            $this->logBuffer[] = $logInfo;
            if (count($this->logBuffer) > $this->options->memoryBufferLines) {
                array_slice($this->logBuffer, -$this->options->memoryBufferLines);
            }
        }

        $logMsg = $this->formatLogMessage($logInfo, $tabLevel);

        /* Log to file */
        if ($this->outputType & self::OUTPUT_FILE && $this->initLogFile()) {
            file_put_contents($this->logDir . $date . '.log', $logMsg . "\n", FILE_APPEND);
        }

        /* Log to console */
        if ($this->outputType & self::OUTPUT_CONSOLE) {
            error_log($logMsg);
        }

        /* Log to OS Syslog */
        if ($this->outputType & self::OUTPUT_SYSLOG) {
            switch ($logLevel) {
                case self::LEVEL_DEBUG :
                case self::LEVEL_TRACE :
                    $priority = LOG_DEBUG;
                    break;
                case self::LEVEL_INFO :
                    $priority = LOG_INFO;
                    break;
                case self::LEVEL_WARNING :
                    $priority = LOG_WARNING;
                    break;
                case self::LEVEL_ERROR :
                    $priority = LOG_ERR;
                    break;
                default :
                    $priority = LOG_INFO;
            }
            syslog($priority, $logMsg);
        }

        if (($this->outputType & self::OUTPUT_CUSTOM) && is_callable($this->options->customFunction)) {
            try {
                ($this->options->customFunction)($logLevel, $log, $tabLevel);
            } catch (Exception $e) {}
        }
    }

    /**
     * Generate a text string including all the information about the log, which includes:
     * <ul>
     * <li>datetime</li>
     * <li>log type (ERROR, WARNING...)</li>
     * <li>Stack information</li>
     * <li>log message</li>
     * </ul>
     *
     * @param LKLogInfo $logInfo
     * @param int $tabLevel
     */
    private function formatLogMessage($logInfo, $tabLevel) {
        $maxFnLength = 30;
        $tabLevel += $this->options->tabStackDepth ? $logInfo->stackDepth : 0;

        $message[] = $logInfo->date;
        $message[] = str_pad(strtoupper($logInfo->level), 7, ' ', STR_PAD_RIGHT);
        if ($logInfo->stackFunction && $this->options->showStack) {
            $message[] = str_pad($logInfo->stackLine, 4, '0', STR_PAD_LEFT);
            $message[] = str_pad($logInfo->stackFunction, $maxFnLength, ' ', STR_PAD_RIGHT);
        }

        $message[] = str_repeat(' ', 2 * $tabLevel) . $logInfo->message;
        return implode(' ', $message);
    }
}