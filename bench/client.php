<?php
// usage: php client.php <socket> <phpstan args...> Runs one PHPStan command in the daemon, exits with its code.
$c = stream_socket_client('unix://' . $argv[1]);
fwrite($c, json_encode(['cwd' => getcwd(), 'argv' => array_merge(['phpstan'], array_slice($argv, 2))]) . "\n");
exit((int) fgets($c));
