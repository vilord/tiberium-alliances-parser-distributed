<?php

error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php';


use limitium\TAPD\CCAuth\CCAuth;
use limitium\TAPD\Register;
use limitium\TAPD\Util\Helper;

$servers = require Helper::pathToServers();

$authorizator = new CCAuth("limitium@gmail.com", "qweqwe123");
$ses = $authorizator->getSession();

$r = new Register();
$r->checkServers($servers, $ses);

