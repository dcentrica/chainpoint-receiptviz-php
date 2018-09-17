<?php

use Dcentrica\Viz\HashUtils as HU;
use PHPUnit\Framework\TestCase;

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package chainpoint-receiptviz-php
 * @license BSD-3
 *
 * Rudimentary tests of HashUtils routines.
 */
class HashUtilsTest extends TestCase
{
    public function testBufferToBin()
    {
        $bytes = [133,196,198,148,13,17,222,115,103,89,124,93,138,127,236,166,83,198,45,168,77,48,182,21,183,248,198,208,137,220,28,121];

        $this->assertGreaterThan(0, strlen(HU::buffer_to_bin($bytes)));
    }

    public function testBufferDigestFrom()
    {
        $bytes = [133,196,198,148,13,17,222,115,103,89,124,93,138,127,236,166,83,198,45,168,77,48,182,21,183,248,198,208,137,220,28,121];

        $this->assertEquals(
            '85c4c6940d11de7367597c5d8a7feca653c62da84d30b615b7f8c6d089dc1c79',
            HU::buffer_digest_from($bytes)
        );
    }

    public function testBufferFrom()
    {
        // UTF8 Mode
        $utf8 = 'node_id:a4c7a7f0-92c9-11e8-ae5b-01a6f6bbeb11';

        $this->assertEquals(
            [110,111,100,101,95,105,100,58,97,52,99,55,97,55,102,48,45,57,50,99,57,45,49,49,101,56,45,97,101,53,98,45,48,49,97,54,102,54,98,98,101,98,49,49],
            HU::buffer_from($utf8, 'utf8')
        );

        // Hex Mode
        $hex = 'c4de952ad40c558d161c9f8a85721bca1c8f3c2c44fda1c40bfc8ab56b692be2';

        $this->assertEquals(
            [196,222,149,42,212,12,85,141,22,28,159,138,133,114,27,202,28,143,60,44,68,253,161,196,11,252,138,181,107,105,43,226],
            HU::buffer_from($hex, 'hex')
        );
    }

    // Basic test, just asserts that the endian direction has been reversed, not what
    // format it wa sin at the start
    public function testSwitchEndian()
    {
        $this->assertEquals(
            'e22b696bb58afc0bc4a1fd442c3c8f1cca1b72858a9f1c168d550cd42a95dec4',
            HU::switch_endian('c4de952ad40c558d161c9f8a85721bca1c8f3c2c44fda1c40bfc8ab56b692be2')
        );

    }

}
