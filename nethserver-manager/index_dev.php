<?php
namespace NethServer;

/*
 * Copyright (C) 2011 Nethesis S.r.l.
 *
 * This script is part of NethServer.
 *
 * NethServer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * NethServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with NethServer.  If not, see <http://www.gnu.org/licenses/>.
 */

// PHP settings
ini_set('log_errors', "1");
ini_set('error_log', NULL);
ini_set('error_reporting', E_ALL | E_STRICT);
ini_set('session.use_trans_sid', "0");
session_cache_limiter(FALSE);
ini_set('display_errors', "0");
ini_set('html_errors', "0");
ini_set('default_mimetype', 'text/plain');
ini_set('default_charset', 'UTF-8');
setlocale(LC_CTYPE, 'en_US.utf-8');
register_shutdown_function(function() {    
    $error = error_get_last();
    if (is_array($error)) {
        header('HTTP/1.1 500 Internal server error');
        $errorMessage = sprintf("\n\n[%s] %s\nFile %s, line %s\n", $error['type'], $error['message'], $error['file'], $error['line']);
        syslog(LOG_ERR, trim(strtr($errorMessage, "\n", " ")));
        echo $errorMessage;
    }
});


if (FALSE || ! file_exists(__DIR__ . '/debug')) {
    header('HTTP/1.1 403 Forbidden');
    echo "Access denied";
    exit;
}

// Disable hashed target names:
define('NETHGUI_ENABLE_TARGET_HASH', FALSE);

// Enable nethgui javascript files auto inclusion:
define('NETHGUI_ENABLE_INCLUDE_WIDGET', TRUE);

// Disable caching:
// define('NETHGUI_ENABLE_HTTP_CACHE_HEADERS', FALSE);
// Enable debug mode (produces more verbose log
// output and uses non-minified js & css)
define('NETHGUI_DEBUG', TRUE);

$namespaces = array();
include_once("autoload.php");
$nsbase = dirname(__DIR__);
$loader = new \Composer\Autoload\ClassLoader();
$loader->add('Nethgui',  $nsbase);
$loader->add('NethServer', $nsbase);
$loader->register();
foreach ($loader->getPrefixes() as $nsName => $paths) {
    $namespaces[trim($nsName, '\\')] = reset($paths) . DIRECTORY_SEPARATOR . trim($nsName, '\\');
}
$loader->add('Pimple', $nsbase);
$loader->add('Mustache', $nsbase);
$loader->add('Symfony', $nsbase);

$FW = new \Nethgui\Framework();
$FW
    ->setLogLevel(E_WARNING | E_ERROR | E_NOTICE)
    ->registerNamespace($namespaces[__NAMESPACE__])
    ->setDefaultModule('Dashboard')
    ->setDecoratorTemplate('NethServer\\Template\\NethServer')
    ->setForcedLoginModule(function($dc) {
        return $dc['Platform']->getDatabase('configuration')->getProp('httpd-admin', 'ForcedLoginModule');
    })
    ->setAuthenticationValidator(function($dc) {
        $v = new \NethServer\Tool\PamValidator();
        $v->setLog($dc['Log']);
        return $v;
    })
;

try {
    $R = $FW->createRequest();
    if ($R->getFormat() === 'xhtml') {
        $R
            ->setParameter('Menu', array())
            ->setParameter('Tracker', array())
            ->setParameter('Notification', array())
            ->setParameter('Resource', array())
            ->setParameter('Logout', array())            
        ;
    } elseif ($R->getFormat() === 'json') {
        $R
            ->setParameter('Tracker', array())
            ->setParameter('Notification', array());
    }
    $FW->dispatch($R);
} catch (\Nethgui\Exception\HttpException $ex) {
    $FW->printHttpException($ex, TRUE);
} catch (\Exception $ex) {
    $FW->printHttpException(new \Nethgui\Exception\HttpException('Internal server error', 500, 1377609335, $ex), TRUE);
}


