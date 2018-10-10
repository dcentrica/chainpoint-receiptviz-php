#!/usr/bin/php
<?php

require(realpath(__DIR__ . '/chainpoint-receiptviz-php/src/Viz/ChainpointViz.php'));
require(realpath(__DIR__ . '/chainpoint-receiptviz-php/src/Viz/HashUtils.php'));

$receipt = file_get_contents('chainpoint.json');

$viz = new \Dcentrica\Viz\ChainpointViz();
$viz->setChain('bitcoin');
$viz->setReceipt($receipt);
$viz->setFilename(realpath(__DIR__) . '/chainpoint.svg');
$viz->setExplorer('smartbit.com.au');
$viz->visualize();
