<?php

namespace Dcentrica\Viz;

use \Exception;
use Dcentrica\Viz\HashUtils as HU;

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package chainpoint-viz
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
     * The value used to decide from how many ops behind the first occurrence of a
     * sha256d ('sha-256-x2') hash, we need to go to obtain the OP_RETURN value.
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
        $currHashVal = HU::buffer_from($this->getHash(), 'hex');
        $currHashViz = HU::buffer_digest_from($currHashVal);

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
            $currHashViz,
            '%s',
            '%s'
        );

        // Process the desired "ops" arrays
        $ops = $this->getOps();
        $total = sizeof($ops[0]) + sizeof($ops[1]);

        // Init the dotfile's sections
        $dotFileArr = ['s1' => [], 's2' => []];

        // Marker used to help calculate the value in BTC's OP_RETURN
        $isFirst256x2 = false;
        $i = 1;

        foreach ($ops as $data) {
            foreach ($data as $val) {
                list ($op, $val) = [key($val), current($val)];

                if ($op === 'r') {
                    // Hex data is treated as hex. Otherwise it's converted to bytes assuming a utf8 encoded string
                    $concatValue = HU::is_hex($val) ? HU::buffer_from($val, 'hex') : HU::buffer_from($val, 'utf8');
                    $currHashVal = HU::buffer_concat($currHashVal, $concatValue);
                    $currHashViz = HU::buffer_digest_from($currHashVal);
                } else if ($op === 'l') {
                    // Hex data is treated as hex. Otherwise it's converted to bytes assuming a utf8 encoded string
                    $concatValue = HU::is_hex($val) ? HU::buffer_from($val, 'hex') : HU::buffer_from($val, 'utf8');
                    $currHashVal = HU::buffer_concat($concatValue, $currHashVal);
                    $currHashViz = HU::buffer_digest_from($currHashVal);
                } else if ($op === 'op') {
                    switch ($val) {
                        case 'sha-256':
                            $currHashVal = HU::buffer_from(hash('sha256', HU::buffer_to_bin($currHashVal)), 'hex');
                            $currHashViz = HU::buffer_digest_from($currHashVal);
                            break;
                        case 'sha-256-x2':
                            // The ID at the location where the OP_RETURN is
                            // the first double-hash, is the ID of the BTC TXID
                            if (!$isFirst256x2) {
                                $isFirst256x2 = true;
                                $btcTxIdOpIndex = ($i - 1);
                                $opReturnIndex = ($btcTxIdOpIndex - self::CHAINPOINT_OP_POINT);
                            }

                            $currHashVal = HU::buffer_from(hash('sha256', HU::buffer_to_bin($currHashVal)), 'hex');
                            $currHashVal = HU::buffer_from(hash('sha256', HU::buffer_to_bin($currHashVal)), 'hex');
                            $currHashViz = HU::buffer_digest_from($currHashVal);
                            break;
                    }
                } else if ($op === 'anchors') {
                    if ($val[0]['type'] !== 'cal') {
                        // Merkle Root
                        $currHashViz = HU::switch_endian(HU::buffer_digest_from($currHashVal));
                    }
                }

                $currNodeIdx = $i;
                $nextNodeIdx = ($currNodeIdx + 1);

                // Build section 1 of the dotfile
                if (($nextNodeIdx - 1)  <= $total) { // subtract 1 as we omit cal's "anchors" array
                    $dotFileArr['s1'][] = sprintf(
                        'node%d [ label = "<f0> | <f1> %s | <f2> "];',
                        $currNodeIdx,
                        $currHashViz
                    );
                }

                // Build section 2 of the dotfile
                if (($nextNodeIdx - 1) < $total) {  // subtract 1 as we omit cal's "anchors" array
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
     * Returns the chainpoint version from the passed $receipt.
     *
     * @param  array $receipt A JSON decoded array of a chainpoint receipt.
     * @return int
     */
    public function chainpointVersion(array $receipt) : int
    {
        return (int) preg_replace('#[^\d]+#', '', explode('/', $receipt['@context'])[4]);
    }

    /**
     * Flattens the branches structure from the chainpoint receipt format to
     * make it easier to work with.
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

        if ($this->chainpointVersion($receipt) !== 3) {
            throw new Exception('Invalid receipt! Only v3 receipts are currently supported.');
        }

        $ops = [];

        foreach ($receipt['branches'][0] as $ckey => $cval) {
            if ($ckey === 'ops') {
                // Gives us all CAL ops and anchors
                $ops[] = $cval;
            } else if ($ckey === 'branches') {
                foreach ($cval[0] as $bckey => $bcval) {
                    // Gives us all BTC ops and anchors (and any others added in the future)
                    if ($bckey === 'ops') {
                        $ops[] = $bcval;
                    }
                }
            }
        }

        return $ops;
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
