<?php

class Logger
{
    /**
     * @var LoggerHandler
     */
    private static $loggerHandler;

    public static function setLoggerHandler(LoggerHandler $logger)
    {
        self::$loggerHandler = $logger;
    }

    public static function debug($message)
    {
        self::$loggerHandler->debug($message);
    }

    public static function info($message)
    {
        self::$loggerHandler->info($message);
    }

    public static function warn($message)
    {
        self::$loggerHandler->warn($message);
    }

    public static function error($message)
    {
        self::$loggerHandler->error($message);
    }

    public static function log($levelName, $message)
    {
        self::$loggerHandler->log($levelName, $message);
    }

    public static function getInstance()
    {
        return self::$loggerHandler;
    }
}

interface LoggerHandler
{
    public function __construct($logDirectory, $logFileName, $configFilePath, $consoleOverride);

    public function debug($message);

    public function info($message);

    public function warn($message);

    public function error($message);

    public function log($levelName, $message);

    public function locateConfigFile($filePath);
}

class SimpleLogger implements LoggerHandler
{
    const DEBUG = 1;
    const INFO = 2;
    const WARN = 3;
    const ERROR = 4;
    const FILEOUTPUT = 'file';
    const CONSOLEOUTPUT = 'console';
    const ARCHIVESDIR = 'archives';

    private static $instance;

    private $levels = array(
        'DEBUG' => SimpleLogger::DEBUG,
        'INFO' => SimpleLogger::INFO,
        'WARN' => SimpleLogger::WARN,
        'ERROR' => SimpleLogger::ERROR
    );
    private $file;
    private $logDirectory;
    private $logFilename;
    private $dateFormat = "H:i:s d/m/y";
    private $config;
    /**
     * From a config file
     * ex:
     * [file]
     * level = debug
     * [console]
     * level = error
     * note: console output is sent by email when it's running as cron
     */
    private $levelsFromConfig;

    public function __construct($logDirectory, $logFileName, $configFilePath = null, $consoleOverride = false)
    {
        date_default_timezone_set("America/Montreal");
        $this->logDirectory = $logDirectory;
        $this->logFilename = $logFileName;
        $logFilePath = $logDirectory . '/' . $logFileName;

        $this->assertDirectoryExists($logDirectory);
        $this->rotateLog();

        if (!$configFilePath) {
            $this->locateConfigFile("logger.ini");
        } else {
            $this->locateConfigFile($configFilePath);
        };
        $this->setLevelForOutput();
        if ($consoleOverride) {
            $this->levelsFromConfig['console'] = $this->levels[strtoupper($consoleOverride)];
        }
        $this->file = fopen($logFilePath, 'a');
        if (!$this->file) {
            echo "Check your main php error log\n";
            $message = sprintf("Error while trying to write to log file %s", $logFilePath);
            error_log($message);
            die();
        }

        self::$instance = $this;
    }

    public static function getInstance()
    {
        return self::$instance;
    }

    private function rotateLog()
    {
        $logFilePath = $this->logDirectory . '/' . $this->logFilename;
        $today = date('d');
        $timestamp = @filemtime($this->logDirectory . '/' . $this->logFilename);

        if ($timestamp) {
            $lastOpened = date('d', $timestamp);
            $archivesDirectoryFullPath = $this->logDirectory . '/' . SimpleLogger::ARCHIVESDIR;

            $this->assertDirectoryExists($archivesDirectoryFullPath);

            if (strcmp($today, $lastOpened) != 0) {
                $logFileNameWithDate =
                    substr($this->logFilename, 0, strpos($this->logFilename, '.'))
                    . '-' .
                    date('y-m-d', $timestamp) . '.log';
                exec(sprintf("mv %s %s\n", $logFilePath, $archivesDirectoryFullPath . '/' . $logFileNameWithDate));
            }
        }
    }

    private function assertDirectoryExists($dirAbsolute)
    {
        $foundDir = @dir($dirAbsolute);
        if (!$foundDir) {
            mkdir($dirAbsolute);
        }
    }

    public function debug($message)
    {
        $this->log("DEBUG", $message);
    }

    public function info($message)
    {
        $this->log("INFO", $message);
    }

    public function warn($message)
    {
        $this->log("WARN", $message);
    }

    public function error($message)
    {
        $this->log("ERROR", $message);
    }

    public function log($levelName, $message)
    {
        $levelNumber = $this->levels[$levelName];
        $line = sprintf("[%s] %s: %s\n", date($this->dateFormat), $levelName, $message);
        if ($levelNumber >= $this->levelsFromConfig[SimpleLogger::CONSOLEOUTPUT]) {
            echo $line;
        }
        if ($levelNumber >= $this->levelsFromConfig[SimpleLogger::FILEOUTPUT]) {
            fwrite($this->file, $line);
        }
    }

    public function __destruct()
    {
        fclose($this->file);
    }

    public function locateConfigFile($filePath)
    {
        $this->config = parse_ini_file($filePath, true);
        if (!$this->config) {
            $message = "Invalid config file and path, check it";
            error_log($message);
        }
    }

    private function setLevelForOutput()
    {
        $this->levelsFromConfig = array();
        foreach ($this->config as $outputName => $output) {
            $this->levelsFromConfig[$outputName] = $this->levels[strtoupper($output['level'])];
        }
    }
}

class FakeLogger implements LoggerHandler
{

    private static $instance;

    public static function getInstance()
    {
        return self::$instance;
    }

    public function __construct($logDirectory = null, $logFileName = null, $configFilePath = null,
        $consoleOverride = null)
    {
        self::$instance = $this;
    }

    public function debug($message)
    {
    }

    public function info($message)
    {
    }

    public function warn($message)
    {
    }

    public function error($message)
    {
    }

    public function log($levelName, $message)
    {
        echo $levelName, $message;
    }

    public function locateConfigFile($filePath)
    {
    }
}

function errorHandler($errno, $errstr, $errfile, $errline)
{
    if (!is_null(Logger::getInstance())) {
        switch ($errno) {
            case E_NOTICE:
            case E_USER_NOTICE:
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
            case E_STRICT:
                Logger::info(sprintf("Notice: %s on line %s : %s", $errfile, $errline, $errstr));
                break;

            case E_WARNING:
            case E_USER_WARNING:
                Logger::warn(sprintf("File: %s on line %s : %s", $errfile, $errline, $errstr));
                break;

            case E_ERROR:
            case E_USER_ERROR:
                Logger::error(sprintf("File: %s on line %s : %s", $errfile, $errline, $errstr));
                exit();

            default:
                Logger::error(sprintf("Unknown error : %s on line %s", $errfile, $errline));
                exit();
        }
    } else {
        trigger_error("No SimpleLogger instance");
    }
}

set_error_handler("errorHandler");

