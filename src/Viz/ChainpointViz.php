<?php

namespace Dcentrica\Viz;

use \Exception;
use Dcentrica\Viz\HashUtils as HU;
use Dcentrica\Graphviz\GraphvizUtils as GU;

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package chainpoint-receiptviz-php
 * @license BSD-3
 *
 * Works with v3 Chainpoint Receipts and the Graphviz libraries to produce simple
 * visual representations of chainpoint data in any image format supported by
 * Graphviz itself.
 *
 * Hat-tip to the chainpoint/chainpoint-parse JS project for guidance on how to
 * construct hashes in accordance with a chainpoint JSON-LD proof.
 * @see https://github.com/chainpoint/chainpoint-parse.
 */
class ChainpointViz
{

    /**
     * The value used to decide from how many ops behind the first occurrence of a
     * sha256d ('sha-256-x2') hash, we need to go to obtain the OP_RETURN value.
     *
     * @const int
     */
    const CHAINPOINT_OP_POINT = 3;

    /**
     * Configuration params, modified by individual setters.
     *
     * @var string
     */
    protected $chain = 'bitcoin';
    protected $receipt = '';
    protected $format = 'png';
    protected $filename = 'chainpoint';

    private $has256x2 = false;
    private $btcTxIdOpIndex = 0;

    /**
     * @param  string $receipt The Chainpoint Proof JSON document as a JSON string.
     * @param  string $chain   The blockchain backend to use e.g. 'bitcoin'.
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
     * Simple getter.
     *
     * @return string The current chainpoint receipt
     */
    public function getReceipt(): string
    {
        return $this->receipt;
    }

    /**
     * Simple getter.
     *
     * @return string The current chain
     */
    public function getChain(): string
    {
        return $this->chain;
    }

    /**
     * Build an HTML table of metadata pertinent to the current receipt.
     *
     * @return string
     */
    private function metaTable() : string
    {
        $data = [
            'NIST Timestamp' => 'foo',
            'Chainpoint Submission Date' => json_decode($this->getReceipt(), true)['hash_submitted_node_at'],
            'BTC Timestsamp' => 'foobar',
        ];

        return GU::table($data);
    }

    /**
     * Processes all the branch arrays from the chainpoint receipt and generates
     * a dot file for consumption by the GraphViz "dot" utility.
     *
     * The dot file is consumed by the Graphviz `dot` program to generate graphical
     * representations of a chainpoint proof in any image format supported by Graphviz
     * itself.
     *
     * This logic has been adapted for PHP from the github.com/chainpoint/chainpoint-parse
     * project.
     *
     * @return string    A stringy representation of a dotfile for use by Graphviz.
     * @throws Exception
     */
    public function parseBranches(): string
    {
        $currHashVal = HU::buffer_from($this->getHash(), 'hex');
        $currHashViz = HU::buffer_digest_from($currHashVal);

        // Output graphic fill styles
        $nodeStyle = false;
        // Highlights for key items:
        // 1. Start Hash
        // 2. Merkle Root
        // 3. OP_RETURN
        $nodeStyleMarkup = ',style="filled", fillcolor="#000000", fontcolor="#FFFFFF"';
        $receipt = json_decode($this->getReceipt(), true);

        // Prepare a dot file template
        $dotTpl = sprintf(implode(PHP_EOL, [
            'digraph G {',
            '%s',
            'labelloc="t";',
            'label="Visual Representation of a v%d Chainpoint Proof"',
            '// Generated on: %s',
            'node [shape="record"]',
            'node0 [label="<f0>Start Hash:|<f1>%s" %s];',
            '%s',
            '"node0":f1 -> "node1":f1;',
            '%s',
            '}'
            ]),
            $this->metaTable(),
            $this->chainpointVersion($receipt),
            date('Y-m-d H:i:s'),
            $currHashViz,
            $nodeStyleMarkup,
            '%s',
            '%s'
        );

        // Process the desired "ops" arrays
        $ops = $this->getOps();
        $total = sizeof($ops[0]) + sizeof($ops[1]);
        $btcAnchorInfo = [];

        // Init the dotfile's sections
        $dotFileArr = ['s1' => [], 's2' => []];
        // Start at 1 as we've already pushed the start-hash onto the dotfile
        $i = 1;

        foreach ($ops as $data) {
            foreach ($data as $val) {
                list ($op, $val) = [key($val), current($val)];

                if ($op === 'r') {
                    $nodeStyle = false;
                    $label = 'Concat (RHS):';
                    // Hex data is treated as hex. Otherwise it's converted to bytes assuming a utf8 encoded string
                    $concatValue = HU::is_hex($val) ? HU::buffer_from($val, 'hex') : HU::buffer_from($val, 'utf8');
                    $currHashVal = HU::buffer_concat($currHashVal, $concatValue);
                    $currHashViz = HU::buffer_digest_from($currHashVal);
                } else if ($op === 'l') {
                    $label = 'Concat (LHS):';
                    $nodeStyle = false;
                    // Hex data is treated as hex. Otherwise it's converted to bytes assuming a utf8 encoded string
                    $concatValue = HU::is_hex($val) ? HU::buffer_from($val, 'hex') : HU::buffer_from($val, 'utf8');
                    $currHashVal = HU::buffer_concat($concatValue, $currHashVal);
                    $currHashViz = HU::buffer_digest_from($currHashVal);
                } else if ($op === 'op') {
                    $nodeStyle = false;
                    $label = sprintf('OP (%s):', $val);

                    switch ($val) {
                        case 'sha-256':
                            $currHashVal = HU::buffer_from(hash('sha256', HU::buffer_to_bin($currHashVal)), 'hex');
                            $currHashViz = HU::buffer_digest_from($currHashVal);
                            break;
                        case 'sha-256-x2':
                            $currHashVal = HU::buffer_from(hash('sha256', HU::buffer_to_bin($currHashVal)), 'hex');
                            $currHashVal = HU::buffer_from(hash('sha256', HU::buffer_to_bin($currHashVal)), 'hex');
                            $currHashViz = HU::buffer_digest_from($currHashVal);

                            if (!$this->has256x2) {
                                $this->has256x2 = true;
                            }

                            break;
                    }
                } else if ($op === 'anchors') {
                    if ($val[0]['type'] === 'btc') {
                        // Determine the Merkle Root
                        $label = 'Merkle Root:';
                        // Merkle Root
                        $nodeStyle = true;
                        $currHashViz = HU::switch_endian(HU::buffer_digest_from($currHashVal));
                    }
                }

                $currNodeIdx = $i;
                $nextNodeIdx = ($currNodeIdx + 1);

                // Build section 1 of the dotfile
                if (($nextNodeIdx - 1)  <= $total) { // subtract 1 (We've omitted cal's "anchors" array)
                    $dotFileArr['s1'][] = sprintf(
                        'node%d [ label="<f0>%s|<f1>%s" %s ];',
                        $currNodeIdx,
                        $label,
                        $currHashViz,
                        $nodeStyle ? $nodeStyleMarkup : ''
                    );
                }

                // Build section 2 of the dotfile
                if (($nextNodeIdx - 1) < $total) {  // subtract 1 (We've omitted cal's "anchors" array)
                    $dotFileArr['s2'][] = sprintf(
                        '"node%d":f1 -> "node%d":f1;',
                        $currNodeIdx,
                        $nextNodeIdx
                    );
                }

                $i++;
            }

            // Derive the OP_RETURN value from the value at the first sha-256-x2
            if (!$this->has256x2) {
                $btcAnchorInfo = $this->getBtcAnchorInfo($currHashViz, $this->getOps()[1]);

                // Append OP_RETURN to the dotfile's sections
                array_push($dotFileArr['s1'], sprintf(
                    'node%d [ label="<f0>OP_RETURN:|<f1>%s" %s ];',
                    ($total + 1),
                    $btcAnchorInfo['opr'],
                    $nodeStyleMarkup
                ));
                array_push($dotFileArr['s2'], sprintf(
                    '"node%d":f0 -> "node%d":f0;',
                    (sizeof($this->getOps()[0]) + $this->btcTxIdOpIndex),
                    ($total + 1)
                ));
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
     * Parse the BTC ops-branch in order to calculate the OP_RETURN and BTC TXID
     * values.
     *
     * @param  string $startHash
     * @param  array  $btcOps
     * @return array
     */
    public function getBtcAnchorInfo(string $startHash, array $btcOps) : array
    {
        $currHashVal = HU::buffer_from($startHash, 'hex');
        $opResultTable = [];
        $i = 0;

        foreach ($btcOps as $val) {
            list ($op, $val) = [key($val), current($val)];

            if ($op === 'r') {
                // Hex data is treated as hex. Otherwise it's converted to bytes assuming a utf8 encoded string
                $concatValue = HU::is_hex($val) ? HU::buffer_from($val, 'hex') : HU::buffer_from($val, 'utf8');
                $currHashVal = HU::buffer_concat($currHashVal, $concatValue);
                $currHashViz = HU::buffer_digest_from($currHashVal);
                $opResultTable[] = [
                    'bin' => $currHashVal,
                    'hex' => $currHashViz,
                ];
            } else if ($op === 'l') {
                // Hex data is treated as hex. Otherwise it's converted to bytes assuming a utf8 encoded string
                $concatValue = HU::is_hex($val) ? HU::buffer_from($val, 'hex') : HU::buffer_from($val, 'utf8');
                $currHashVal = HU::buffer_concat($concatValue, $currHashVal);
                $currHashViz = HU::buffer_digest_from($currHashVal);
                $opResultTable[] = [
                    'bin' => $currHashVal,
                    'hex' => $currHashViz,
                ];
            } else if ($op === 'op') {
                switch ($val) {
                    case 'sha-256':
                        $currHashVal = HU::buffer_from(hash('sha256', HU::buffer_to_bin($currHashVal)), 'hex');
                        $currHashViz = HU::buffer_digest_from($currHashVal);
                        $opResultTable[] = [
                            'bin' => $currHashVal,
                            'hex' => $currHashViz,
                        ];
                        break;
                    case 'sha-256-x2':
                        $currHashVal = HU::buffer_from(hash('sha256', HU::buffer_to_bin($currHashVal)), 'hex');
                        $currHashVal = HU::buffer_from(hash('sha256', HU::buffer_to_bin($currHashVal)), 'hex');
                        $currHashViz = HU::buffer_digest_from($currHashVal);

                        if (!$this->btcTxIdOpIndex) {
                            $this->btcTxIdOpIndex = $i;
                        }

                        $opResultTable[] = [
                            'bin' => $currHashVal,
                            'hex' => $currHashViz,
                        ];
                        break;
                }
            }

            $i++;
        }

        // Calculate from where to get the OP_RETURN value
        $oprIdx = ($this->btcTxIdOpIndex - self::CHAINPOINT_OP_POINT);

        return [
            'opr' => $oprIdx >0 ? $opResultTable[$oprIdx]['hex'] : null,
            'txid' => null,
        ];
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
