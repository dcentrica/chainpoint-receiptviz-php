<?php

namespace Dcentrica\ReceiptViz;

use \Exception;

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package dcentrica-chainpoint-tools
 * @license BSD-3
 *
 * Works with v3 Chainpoint Receipts and Graphviz libraries to produce simple
 * visual representations of chainpoint data in any image format supported by
 * Graphviz itself.
 *
 * Hat-tip to the chainpoint-parse JS project for guidance on how to construct
 * hashes in accordance with a chainpoint proof.
 * @see https://github.com/chainpoint/chainpoint-parse
 */
class ReceiptViz
{

    /**
     * @var string
     */
    protected $chain = 'bitcoin';
    protected $receipt = '';
    protected $format = 'png';
    protected $filename = 'chainpoint';
    protected $root = '';

    /**
     * @param  string $proof The Chainpoint Proof JSON document as a JSON string.
     * @param  string $chain The blockchain backend to use e.g. 'bitcoin'.
     * @return void
     */
    public function __construct(string $receipt = '', string $chain = '')
    {
        if (!self::which('dot')) {
            throw new Exception('Graphviz dot program not available!');
        }

        $this->setReceipt($receipt);
        $this->setChain($chain);
    }

    /**
     * Set the current valid blockchain network for working with.
     *
     * @param  string $chain e.g. 'bitcoin'
     * @return ReceiptViz
     */
    public function setChain(string $chain): ReceiptViz
    {
        $this->chain = $chain;

        return $this;
    }

    /**
     * Set the current Chainpoint receipt for working with.
     *
     * @param  string $receipt
     * @return ReceiptViz
     */
    public function setReceipt(string $receipt): ReceiptViz
    {
        $this->receipt = $receipt;

        return $this;
    }

    /**
     * Set the desired output image format.
     *
     * @param  string $format Can be any format supported by Graphviz.
     * @return ReceiptViz
     */
    public function setFormat(string $format): ReceiptViz
    {
        $this->format = $format;

        return $this;
    }

    /**
     * Set the desired output image filename. Defaults to "chainpoint.png" and
     * saves to the current working directory.
     *
     * @param  string $filename.
     * @return ReceiptViz
     */
    public function setFilename(string $filename): ReceiptViz
    {
        $this->filename = $filename;

        return $this;
    }

    /**
     * Set the desired Merkle Root for display. This isn't available directly from
     * Chainpoint JSON.
     *
     * @param  string $root  The Merkle Root hash as stored on a blockchain.
     * @return ReceiptViz
     */
    public function setRoot(string $root): ReceiptViz
    {
        $this->root = $root;

        return $this;
    }

    /**
     * @return string The current chainpoint receipt
     */
    public function getReceipt(): string
    {
        return $this->receipt;
    }

    /**
     * @return string The current chain
     */
    public function getChain(): string
    {
        return $this->chain;
    }

    /**
     * Internal pointer store.
     *
     * @var string
     */
    private $currHashVal;

    /**
     * Generate a dot file for consumption by the GraphViz "dot" utility.
     * The dot file is then used by the dot program to generate graphical representations
     * of a chainpoint proof in almost any supported image format.
     *
     * @return string    A stringy representation of a dotfile for use by Graphviz.
     * @throws Exception
     */
    public function toDot(): string
    {
        if (!$this->root) {
            throw new Exception('Merkle root not set');
        }

        $initHashVal = $this->getHash();

        // Prepare a dot file template
        $template = implode(PHP_EOL, [
            'digraph G {',
            '// Generated: ' . date('Y-m-d H:i:s'),
            'node [shape = record]',
            "node0 [ label =\"<f0> | <f1> {$initHashVal} | <f2> \"];",
            '%s',
            "\"node0\":f1 -> \"node1\":f1;",
            '%s',
            '}'
        ]);

        // Let's get and process the "ops" array
        $method = sprintf('get%sOps', $this->getChain());
        $ops = $this->$method();
        $total = sizeof($ops);
        $dotFileArr = ['s1' => [], 's2' => []];
        $i = 0;

        // Assumes hex data
        foreach ($ops as $op => $val) {
            $op = key($val);
            $val = current($val);

            // TODO unset this from the array itself instead
            if (is_array($val)) {
                continue;
            }

            if ($op === 'r') {
                $this->currHashVal = ($this->currHashVal ?? $initHashVal) . $val;
            } else if ($op === 'l') {
                $this->currHashVal = $val . ($this->currHashVal ?? $initHashVal);
            } else if ($op === 'op') {
                $this->currHashVal = self::hash($val, $this->currHashVal);
            }

            $currNodeIdx = ($i + 1);
            $nextNodeIdx = ($currNodeIdx + 1);
            $dotFileArr['s1'][] = "node$currNodeIdx [ label =\"<f0> | <f1> {$this->currHashVal} | <f2> \"];" . PHP_EOL;

            if ($nextNodeIdx < $total) {
                $dotFileArr['s2'][] = "\"node$currNodeIdx\":f1 -> \"node$nextNodeIdx\":f1;" . PHP_EOL;
            }

            ++$i;
        }

        // Assemble the pieces
        return sprintf(
            $template,
            implode('', $dotFileArr['s1']),
            implode('', $dotFileArr['s2'])
        );
    }

    /**
     * Generate an image file derived from a Graphviz dot template, and save it
     * to a pre-determined F/S location.
     *
     * @return int 1 on failure, zero otherwise.
     * @todo Fix so that no bad things come thru userland code via toDot() passed to exec()
     */
    public function visualise(): int
    {
        $format = $this->format;
        $filename = sprintf(
            '%s.%s', str_replace('.', '', $this->filename), strtolower($this->format)
        );
        $dotFile = sprintf('/tmp/%s.dot', hash('sha256', bin2hex(random_bytes(16))));
        file_put_contents($dotFile, $this->toDot());

        $output = [];
        $return = 0;

        exec("dot $dotFile -T$format -o $filename", $output, $return);

        if ($return !== 0) {
            $msg = sprintf(
                'Graphviz failed to produce an output image. Graphviz said: %s', implode("\n", $output)
            );

            throw new Exception($msg);
        }

        return $return;
    }

    /**
     * Alias of visualise(), for our American friends.
     */
    public function visualize(): int
    {
        return $this->visualise();
    }

    /**
     * Gets the bitcoin ops array from the current receipt.
     *
     * @return array
     * @throws Exception
     */
    public function getBitcoinOps(): array
    {
        $receipt = json_decode($this->getReceipt(), true);
        $branch = $receipt['branches'][0]['branches'][0];

        if (empty($branch)) {
            throw new Exception('Invalid receipt! Sub branches not found.');
        }

        if (!isset($branch['label']) || $branch['label'] !== 'btc_anchor_branch') {
            throw new Exception('Invalid receipt! "btc" sub-branch not found.');
        }

        if (empty($branch['ops'])) {
            throw new Exception('Invalid receipt! "btc" ops data not found.');
        }

        return $branch['ops'];
    }

    /**
     * Return the hash from the chainpoint receipt.
     *
     * @return string
     */
    public function getHash(): string
    {
        $receipt = json_decode($this->getReceipt(), true);

        if (empty($receipt['hash'])) {
            throw new Exception('Invalid receipt! hash not found.');
        }

        return $receipt['hash'];
    }

    /**
     * Runs a CLI `which` command for the passed $prog.
     *
     * @param  string $cmd
     * @return bool
     */
    public static function which(string $prog): bool
    {
        $output = [];
        $return = 0;

        exec("which $prog", $output, $return);

        return $return === 0;
    }

    /**
     * Simple hashing in a chainpoint receipt context.
     *
     * @param  string $func The hash function to use.
     * @param  string $subj The string to hash.
     * @return string       The hashed string.
     */
    public function hash(string $func, string $subj): string
    {
        $parts = explode('-', $func);
        $func = "{$parts[0]}{$parts[1]}";

        if (count($parts) === 3) {
            return hash($func, hash($func, $subj));
        }

        return hash($func, $subj);
    }

}
