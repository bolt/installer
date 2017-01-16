<?php

use Bolt\Installer\Controller\Web;
use Symfony\Component\HttpFoundation\Request;

$request = Request::createFromGlobals();
$web = new Web();
$web->index($request);
