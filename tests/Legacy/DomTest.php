<?php

namespace NFePHP\DA\Tests\Legacy;

use NFePHP\DA\Legacy\Dom;
use PHPUnit\Framework\TestCase;

class DomTest extends TestCase
{
    public function testGetChaveKeepsAlphaNumericCharacters(): void
    {
        $dom = new Dom();
        $accessKey = '4226055V6ADB8V000181650410010000151398285936';
        $xml = sprintf('<NFe><infNFe Id="NFe%s"></infNFe></NFe>', $accessKey);
        $dom->loadXMLString($xml);

        $this->assertSame($accessKey, $dom->getChave('infNFe'));
    }
}
