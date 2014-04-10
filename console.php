<?php

require_once __DIR__ . '/vendor/autoload.php';
$console = new Varifort\Console\Application('IntaroCRM History Command');
$console->add(new Varifort\Command\HistoryCommand());
$console->run();