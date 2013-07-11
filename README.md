SimpleLogger
============

A simple self-contained logger tool for PHP > 5.2
It rotates logs at midnight in a subfolder 'archives' inside your defined log directory.

how to use:
new SimpleLogger($folder, $file, [$configFilePath], [$consoleOverride], [$cleanonOpen]);

Console Override: useful when the console on a remote server is set for error and you
want to get the info level through a remote execution.

then get it's instance from within a class:
$logger = SimpleLogger::getInstance();

The SimpleLogger class will look for a logger.ini file or your defined config file with content like:
[file]
level = info
[console]
level = info
