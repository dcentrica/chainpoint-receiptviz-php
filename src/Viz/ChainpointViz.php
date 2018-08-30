<?php

namespace Dcentrica\Viz;

use \Exception;
use Dcentrica\Viz\HashUtils;

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
 *
 * @todo Refactor all configuration setters into a single setOpts($k, $v) method.
 */
class ChainpointViz
{

    /**
     * Configuration params, modified by individual setters.
     *
     * @var string
     */
    protected $chain = 'bitcoin';
    protected $receipt = '';
    protected $format = 'png';
    protected $filename = 'chainpoint';
    protected $root = '';

    /**
     * @var array
     */
    private $currHashVal;

    /**
     * The value used to decide from how many ops behind the first occurrence of a
     * sha256d ('sha-256-x2') hash, we need to go to obtain the Merkle Root.
     *
     * @const int
     */
    const CHAINPOINT_OP_POINT = 3;

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
     * @return ChainpointViz
     */
    public function setChain(string $chain): ChainpointViz
    {
        $this->chain = $chain;

        return $this;
    }

    /**
     * Set the current Chainpoint receipt for working with.
     *
     * @param  string $receipt
     * @return ChainpointViz
     */
    public function setReceipt(string $receipt): ChainpointViz
    {
        $this->receipt = $receipt;

        return $this;
    }

    /**
     * Set the desired output image format.
     *
     * @param  string $format Can be any format supported by Graphviz.
     * @return ChainpointViz
     */
    public function setFormat(string $format): ChainpointViz
    {
        $this->format = $format;

        return $this;
    }

    /**
     * Set the desired output image filename. Defaults to "chainpoint.png" and
     * saves to the current working directory.
     *
     * @param  string $filename.
     * @return ChainpointViz
     */
    public function setFilename(string $filename): ChainpointViz
    {
        $this->filename = $filename;

        return $this;
    }

    /**
     * Set the desired Merkle Root for display. This isn't available directly from
     * Chainpoint JSON.
     *
     * @param  string $root  The Merkle Root hash as stored on a blockchain.
     * @return ChainpointViz
     */
    public function setRoot(string $root): ChainpointViz
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
     * Processes all the branch arrays from the chainpoint receipt and generate
     * a dot file for consumption by the GraphViz "dot" utility.
     *
     * The dot file is consumed by the Graphviz `dot` program to generate graphical
     * representations of a chainpoint proof in any image format supported by Graphviz
     * itself.
     *
     * This logic has been adapted for PHP from the github.com/chainpoint/chainpoint-parse
     * project.
     *
     * @return string         A stringy representation of a dotfile for use by Graphviz.
     * @throws Exception
     */
    public function parseBranches(): string
    {
        $this->currHashVal = $this->getHash();

        // Prepare a dot file template
        $dotTpl = sprintf(implode(PHP_EOL, [
            'digraph G {',
            '// Generated on: %s',
            'node [shape = record]',
            "node0 [ label =\"<f0> | <f1> %s | <f2> \"];",
            '%s',
            "\"node0\":f1 -> \"node1\":f1;",
            '%s',
            '}'
            ]),
            date('Y-m-d H:i:s'),
            $this->currHashVal,
            '%s',
            '%s'
        );

        // Let's get and process the desired "ops" array
        $opsBranch = $this->getOps();
        $total = sizeof($opsBranch);

        // Init the dotfile's sections
        $dotFileArr = ['s1' => [], 's2' => []];

        // Marker used to help calculate the value in BTC's OP_RETURN
        $isFirst256x2 = false;
        $opRet = 0; // OP_RETURN index
        $i = 1;

        foreach ($opsBranch as $key => $data) {
            foreach ($data as $op => $val) {
                if (is_string($op)) {
                    if ($op === 'r') {
                        $this->currHashVal = $this->currHashVal . $val;
                    } else if ($op === 'l') {
                        $this->currHashVal = $val . $this->currHashVal;
                    } else if ($op === 'op') {
                        switch ($algo = $val) {
                            default:
                            case 'sha-256':
                                $this->currHashVal = hash('sha256', $this->currHashVal);
                                break;
                            case 'sha-256-x2':
                                if (!$isFirst256x2) {
                                    $isFirst256x2 = true;
                                    // Cache this index. It's used to calculate the OP_RETURN value
                                    // TODO Write getOpReturn() method
                                    $opRet = ($i - self::CHAINPOINT_OP_POINT);
                                }

                                $this->currHashVal = hash('sha256', hash('sha256', $this->currHashVal));
                                break;
                        }
                    }
                }

                // Build section 1 of the dotfile
                // Use $i + 1 to cater for the "manual" idx zero used in the template
                $currNodeIdx = $i;
                $nextNodeIdx = ($currNodeIdx + 1);

                if ($nextNodeIdx <= $total) {
                    $dotFileArr['s1'][] = sprintf(
                        'node%d [ label = "<f0> | <f1> %s | <f2> "];',
                        $currNodeIdx,
                        $this->currHashVal
                    );
                }

                if ($nextNodeIdx < $total) {
                    $dotFileArr['s2'][] = sprintf(
                        '"node%d":f1 -> "node%d":f1;',
                        $currNodeIdx,
                        $nextNodeIdx
                    );
                }

                $i++;
            }
        }

        // Assemble the two dotfile sections
        return sprintf(
            $dotTpl,
            implode(PHP_EOL, $dotFileArr['s1']),
            implode(PHP_EOL, $dotFileArr['s2'])
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
        file_put_contents($dotFile, $this->parseBranches());

        $output = [];
        $return = 0;

        exec("dot $dotFile -T$format -o $filename", $output, $return);

        if ($return !== 0) {
            $msg = sprintf(
                'Failed to produce an output image. Graphviz said: %s', implode(PHP_EOL, $output)
            );

            throw new Exception($msg);
        }

        return $return;
    }

    /**
     * Alias of visualise(), for our American friends.
     *
     * @return int 1 on failure, zero otherwise.
     */
    public function visualize(): int
    {
        return $this->visualise();
    }

    /**
     * Flattens the branches structure from the chainpoint format, to make it
     * easier to work with.
     *
     * @return array
     * @throws Exception
     */
    public function getOps(): array
    {
        $receipt = json_decode($this->getReceipt(), true);

        if (empty($receipt['branches'][0])) {
            throw new Exception('Invalid receipt! Sub branches not found.');
        }

        $branchStruct = array_merge(
            $receipt['branches'][0]['ops'], // cal
            $receipt['branches'][0]['branches'][0]['ops'] // btc
        );

        return $branchStruct;
    }

    /**
     * Return the initial hash from the chainpoint receipt.
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

}
