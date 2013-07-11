<?php

class SimpleLogger {
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
    private $levelsFromConfig;

    function __construct($logDirectory, $logFileName, $configFilePath = null, $consoleOverride = false, $cleanOnOpen = false) {
        date_default_timezone_set("America/Montreal");
        $this->logDirectory = $logDirectory;
        $this->logFilename = $logFileName;
        $logFilePath = $logDirectory . '/' . $logFileName;

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
        if (!$cleanOnOpen) {
            $this->file = fopen($logFilePath, 'a');
        } else {
            $this->file = fopen($logFilePath, 'w');
        }
        if (!$this->file) {
            echo "Check your main php error log\n";
            $message = sprintf("Error while trying to write to log file %s", $logFilePath);
            error_log($message);
            die();
        }

        self::$instance = $this;
    }

    public static function getInstance() {
        return self::$instance;
    }

    public function rotateLog() {
        $logFilePath = $this->logDirectory . '/' . $this->logFilename;
        $today = date('d');
        $timestamp = @filemtime($this->logDirectory . '/' . $this->logFilename);

        if ($timestamp) {
            $lastOpened = date('d', $timestamp);
            $archivesDirectoryFullPath = $this->logDirectory . '/' . SimpleLogger::ARCHIVESDIR;

            $archivesDir = @dir($archivesDirectoryFullPath);
            if (!$archivesDir) {
                mkdir($archivesDirectoryFullPath);
            }

            if (strcmp($today, $lastOpened) != 0) {
                $logFileNameWithDate = substr($this->logFilename, 0, strpos($this->logFilename, '.')) . '-' . date('y-m-d', $timestamp) . '.log';
                exec(sprintf("mv %s %s\n", $logFilePath, $archivesDirectoryFullPath . '/' . $logFileNameWithDate));
            }
        }
    }

    public function debug($message) {
        $this->log("DEBUG", $message);
    }

    public function info($message) {
        $this->log("INFO", $message);
    }

    public function warn($message) {
        $this->log("WARN", $message);
    }

    public function error($message) {
        $this->log("ERROR", $message);
    }

    public function log($levelName, $message) {
        $levelNumber = $this->levels[$levelName];
        $line = sprintf("[%s] %s: %s\n", date($this->dateFormat), $levelName, $message);
        if ($levelNumber >= $this->levelsFromConfig[SimpleLogger::CONSOLEOUTPUT])
            echo $line;
        if ($levelNumber >= $this->levelsFromConfig[SimpleLogger::FILEOUTPUT])
            fwrite($this->file, $line);
    }

    function __destruct() {
        fclose($this->file);
    }

    public function locateConfigFile($filePath) {
        $this->config = parse_ini_file($filePath, true);
        if (!$this->config) {
            $message = "Invalid config file and path, check it";
            error_log($message);
        }
    }

    private function setLevelForOutput() {
        $this->levelsFromConfig = array();
        foreach ($this->config as $outputName => $output) {
            $this->levelsFromConfig[$outputName] = $this->levels[strtoupper($output['level'])];
        }
    }
}
