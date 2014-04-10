<?php

require_once __DIR__ . '/vendor/autoload.php';
$console = new LeadsPartner\Console\Application('IntaroCRM History Command');
$console->add(new LeadsPartner\Command\HistoryCommand());
$console->run();