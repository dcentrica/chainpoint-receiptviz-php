#!/usr/bin/php
<?php

require(realpath(__DIR__ . '/chainpoint-receiptviz-php/src/Viz/ChainpointViz.php'));
require(realpath(__DIR__ . '/chainpoint-receiptviz-php/src/Viz/HashUtils.php'));

//echo \Dcentrica\ReceiptViz\Crypto::switch_endian('85c4c6940d11de7367597c5d8a7feca653c62da84d30b615b7f8c6d089dc1c79');
//$currVal = \Dcentrica\ReceiptViz\HashUtils::convert_str_bin('08e540f11800783c883aa97c012472a6cd0ca0ad8be52bf87d886acf813fdd6e');
//$currVal = \Dcentrica\ReceiptViz\HashUtils::convert_dec_hex($currVal);
//$concatVal = \Dcentrica\ReceiptViz\HashUtils::convert_str_bin('node_id:a4c7a7f0-92c9-11e8-ae5b-01a6f6bbeb11');
//$concatVal = \Dcentrica\ReceiptViz\HashUtils::convert_dec_hex($concatVal);
//var_dump($currVal,$concatVal);
//die;
//var_dump(\Dcentrica\ReceiptViz\Crypto::convert_hex_dec('08e540f11800783c883aa97c012472a6cd0ca0ad8be52bf87d886acf813fdd6e'));
//die;


$receipt = file_get_contents('chainpoint.json');

$viz = new \Dcentrica\Viz\ChainpointViz();
$viz->setChain('bitcoin');
$viz->setReceipt($receipt);
$viz->setFilename(realpath(__DIR__) . '/chainpoint.svg');
$viz->setExplorer('smartbit.com.au');
$viz->visualize();
