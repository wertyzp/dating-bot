<?php

declare(strict_types=1);

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

$handle = fopen ("php://stdin","r");
$input = "";
while (!feof($handle)) { // Continue reading until the end of the file is reached
    $result = fread($handle, 4096);
    if ($result === false) {
        break;
    }
    $input .= $result;
}
fclose($handle);

$pid = pcntl_fork();
if ($pid == -1) {
    die('could not fork');
} else if ($pid) {
    return 0;
}

chdir(dirname(__DIR__));

$app = require 'bootstrap/app.php';

$longopts = [
    'bot-key:',
];

$options = getopt('', $longopts);

$arg = "--bot-key={$options['bot-key']}";

$kernel = $app->make(
    'Illuminate\Contracts\Console\Kernel'
);

$args = ['artisan', 'telegram:update', "--input=$input", "$arg"];
/** @var \Laravel\Lumen\Console\Kernel */
exit($kernel->handle(new ArgvInput($args), new ConsoleOutput));
