<?php

namespace App\Tests\Tracing;

use App\Ai\SecretRedactor;
use App\Tracing\DbgpValue;
use PHPUnit\Framework\TestCase;

final class DbgpValueTest extends TestCase
{
    public function testConvertsAReturnValue()
    {
        $response = new \SimpleXMLElement(<<<'XML'
            <response xmlns="urn:debugger_protocol_v1" xmlns:xdebug="https://xdebug.org/dbgp/xdebug" command="run" status="break">
                <xdebug:return_value>
                    <property type="array" children="1" numchildren="3" page="0" pagesize="32">
                        <property name="approved" type="bool"><![CDATA[0]]></property>
                        <property name="declineCode" type="string" size="18" encoding="base64"><![CDATA[aW5zdWZmaWNpZW50X2Z1bmRz]]></property>
                        <property name="apiToken" type="string" size="6" encoding="base64"><![CDATA[c2VjcmV0]]></property>
                    </property>
                </xdebug:return_value>
            </response>
            XML);
        $property = $response->children('https://xdebug.org/dbgp/xdebug')->return_value->children('urn:debugger_protocol_v1')->property[0];

        $this->assertSame(['approved' => false, 'declineCode' => 'insufficient_funds', 'apiToken' => '[redacted]'], (new DbgpValue(new SecretRedactor([])))->convert($property));
    }

    public function testShowsServicesByClassAndEntitiesByValue()
    {
        $response = new \SimpleXMLElement(<<<'XML'
            <response xmlns="urn:debugger_protocol_v1" command="context_get">
                <property name="$checkout" type="object" classname="App\Checkout" children="1" numchildren="2">
                    <property name="pricing" type="object" classname="App\Pricing" children="1" numchildren="1"/>
                    <property name="lines" type="array" children="1" numchildren="4"/>
                </property>
                <property name="$customer" type="object" classname="App\Customer" children="1" numchildren="2">
                    <property name="id" type="string" size="5" encoding="base64"><![CDATA[Y2Fyb2w=]]></property>
                    <property name="orders" type="array" children="1" numchildren="3"/>
                </property>
            </response>
            XML);
        $values = new DbgpValue(new SecretRedactor([]));
        [$checkout, $customer] = iterator_to_array($response->children('urn:debugger_protocol_v1')->property, false);

        $this->assertSame('[App\Checkout]', $values->convert($checkout));
        $this->assertSame(['@class' => 'App\Customer', 'id' => 'carol', 'orders' => '[array(3)]'], $values->convert($customer));
    }
}
