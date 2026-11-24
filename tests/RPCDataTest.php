<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/autoload.php';
require_once __DIR__ . '/../libs/YeelightRPC.php';

use PHPUnit\Framework\TestCase;
use Yeelight\RPC_Data;
use Yeelight\RPCException;

/**
 * Tests für Kodierung und Dekodierung der RPC-Objekte (Beispiele aus der Yeelight Inter-Operation Spec).
 */
class RPCDataTest extends TestCase
{
    public function testCommandToJSON(): void
    {
        $Data = new RPC_Data('set_power', ['on', 'smooth', 500], 1);
        $this->assertSame('{"id":1,"method":"set_power","params":["on","smooth",500]}', $Data->ToJSONString());
        $this->assertSame(RPC_Data::$isCommand, $Data->Typ);
    }

    public function testMagicCallSetsMethodAndId(): void
    {
        $Data = new RPC_Data();
        $Data->toggle();
        $this->assertSame('toggle', $Data->Method);
        $this->assertSame([], $Data->Params);
        $this->assertGreaterThan(0, $Data->Id);
        $this->assertSame(RPC_Data::$isCommand, $Data->Typ);
    }

    public function testNewIdsDiffer(): void
    {
        $Ids = [];
        for ($i = 0; $i < 50; $i++) {
            $Ids[] = (new RPC_Data('get_prop', ['power']))->Id;
        }
        $this->assertCount(50, array_unique($Ids));
    }

    public function testResultOk(): void
    {
        $Data = new RPC_Data();
        $this->assertTrue($Data->CreateFromJSONString('{"id":1, "result":["ok"]}'));
        $this->assertSame(RPC_Data::$isResult, $Data->Typ);
        $this->assertSame(1, $Data->Id);
        $this->assertSame(['ok'], $Data->GetResult());
    }

    public function testResultGetProp(): void
    {
        $Data = new RPC_Data();
        $Data->CreateFromJSONString('{"id":1, "result":["on", "", "100"]}');
        $this->assertSame(['on', '', '100'], $Data->GetResult());
    }

    public function testResultCronGet(): void
    {
        $Data = new RPC_Data();
        $Data->CreateFromJSONString('{"id":1, "result":[{"type": 0, "delay": 15, "mix": 0}]}');
        $Result = $Data->GetResult();
        $this->assertCount(1, $Result);
        $this->assertSame(15, ((array) $Result[0])['delay']);
    }

    public function testError(): void
    {
        $Data = new RPC_Data();
        $Data->CreateFromJSONString('{"id":2, "error":{"code":-1, "message":"unsupported method"}}');
        $this->assertSame(RPC_Data::$isResult, $Data->Typ);
        $Result = $Data->GetResult();
        $this->assertInstanceOf(RPCException::class, $Result);
        $this->assertSame(-1, $Result->getCode());
        $this->assertSame('unsupported method', $Result->getMessage());
    }

    public function testEvent(): void
    {
        $Data = new RPC_Data();
        $Data->CreateFromJSONString('{"method":"props","params":{"power":"on", "bright":"10"}}');
        $this->assertSame(RPC_Data::$isEvent, $Data->Typ);
        $this->assertNull($Data->Id);
        $this->assertSame('props', $Data->Method);
        $this->assertSame(['power' => 'on', 'bright' => '10'], (array) $Data->Params);
    }

    public function testInvalidJSON(): void
    {
        $Data = new RPC_Data();
        $this->assertFalse($Data->CreateFromJSONString('{"id":1, "result":'));
        $this->assertFalse($Data->CreateFromJSONString('"text"'));
    }
}
