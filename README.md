# What is this?

A tool used to generate graphical representations of a [Chainpoint Proof](https://chainpoint.org) JSON-LD document.

# Requirements

* [PHP7.0](https://secure.php.net/) or higher.
* [Graphviz](https://graphviz.org/).

# Installation

    #> composer require dcentrica/chainpoint-receiptviz-php

# Usage

    <?php
    // Use an autoloader instead!
    require(realpath(__DIR__ . '/dcentrica-chainpo/src/ReceiptViz/ReceiptViz.php'));

    $receipt = file_get_contents('chainpoint-proof.json');

    // Generate an SVG file named "/tmp/chainpoint.svg"
    // from a valid chainpont receipt.
    $viz = new \Dcentrica\ReceiptViz\ReceiptViz();
    $viz->setChain('bitcoin');
    $viz->setReceipt($receipt);
    $viz->setFormat('svg');
    $viz->setFilename('/tmp/chainpoint');
    // You'd fetch this via blockchain.info or programmatically via the "anchors" element in any chainpoint proof
    $viz->setRoot(hash('sha256', 'thisismymerkleroot'));

    $viz->visualize();

    ?>

# Credits

Kudos to the [Tierion](https://tierion.com/) guys, especially for the [chainpoint-parse](https://github.com/chainpoint/chainpoint-parse) JS lib which led me to understand how
a chainpoint document is put together.
