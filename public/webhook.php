<?php
require (__DIR__ . "/../vendor/autoload.php");

use GitHubWebhook\Handler;

putenv("HOME=" . __DIR__ . "/../");

$handler = new Handler("ahYiephohP4u", __DIR__ . "/../");
$composer = __DIR__ . "/../composer";
if (! is_file($composer)) {
    copy("https://getcomposer.org/composer.phar", $composer);
    chmod($composer, 0775);
}

if ($handler->handle()) {
    exec("{$composer} update -q -d " . __DIR__);
    echo "OK";
} else {
    echo "Wrong secret";
}
openlog("php-github-webhook", LOG_NDELAY | LOG_PID, LOG_USER);
array_walk($handler->getGitOutput(), function ($line) {
    syslog(LOG_DEBUG, $line);
});
