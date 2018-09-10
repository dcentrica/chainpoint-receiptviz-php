## What is this?

A low level tool which can be used to generate graphical representations of a [Chainpoint Proof](https://chainpoint.org) JSON-LD document. It partially mimics the behaviour of the `parseBranches()` function
out of the [chainpoint-parse](https://github.com/chainpoint/chainpoint-parse) JS lib, but generates a visualisation also.

## Requirements

* [PHP7.0](https://secure.php.net/) or higher.
* [Graphviz](https://graphviz.org/).

## Installation

Composer not yet available..just clone it.

    #> composer require dcentrica/chainpoint-receiptviz

## Notes

There are several chainpoint specification versions, with a version 4 currently under development. This library only supports the current v3 standard. Having said that, it shouldn't be too hard to modify the library to suit other versions.

## Usage

    <?php
    // Use an autoloader instead!
    require(realpath(__DIR__ . '/dcentrica-chainpo/src/ReceiptViz/ReceiptViz.php'));

    $receipt = file_get_contents('chainpoint-proof.json');

    // Generate an SVG file named "/tmp/chainpoint.svg"
    // from a valid chainpont receipt.
    $viz = new \Dcentrica\Viz\ChainpointViz();
    $viz->setChain('bitcoin');
    $viz->setReceipt($receipt);
    $viz->setFormat('svg');
    $viz->setFilename('/tmp/chainpoint');
    
    // Visualise all the things..
    $viz->visualise();

## Credits

Kudos to the [Tierion](https://tierion.com/) guys, especially for the [chainpoint-parse](https://github.com/chainpoint/chainpoint-parse) JS lib which led me to understand how
a chainpoint document is put together.
