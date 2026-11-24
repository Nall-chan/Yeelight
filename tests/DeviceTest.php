<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/autoload.php';

use PHPUnit\Framework\TestCase;

/**
 * Datenfluss-Tests der Yeelight Device Instanz mit den Symcon-Stubs (ohne Gerät und ohne Parent).
 */
class DeviceTest extends TestCase
{
    private const ModuleID = '{BF5D53BB-EB4E-45C0-8632-5DB4EF49FA9F}';

    public function setUp(): void
    {
        IPS\Kernel::reset();
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/../library.json');
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/stubs/CoreStubs/library.json');
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/stubs/IOStubs/library.json');
        parent::setUp();
    }

    public function testEventCreatesStatusVariables(): void
    {
        [$Iid, $Intf] = $this->CreateDevice();
        $this->ReceiveLine($Intf, '{"method":"props","params":{"power":"on","bright":42,"ct":4000,"rgb":16711680,"flowing":1}}');
        $this->assertTrue(GetValue($this->Id($Iid, 'power')));
        $this->assertSame(42, GetValue($this->Id($Iid, 'bright')));
        $this->assertSame(4000, GetValue($this->Id($Iid, 'ct')));
        $this->assertSame(16711680, GetValue($this->Id($Iid, 'rgb')));
        $this->assertTrue(GetValue($this->Id($Iid, 'flowing')));
    }

    public function testEventSplitOverPackets(): void
    {
        [$Iid, $Intf] = $this->CreateDevice();
        $Line = '{"method":"props","params":{"bright":"77"}}' . "\r\n";
        $Intf->ReceiveData(json_encode(['DataID' => '{018EF6B5-AB94-40C6-AA53-46943E824ACF}', 'Buffer' => bin2hex(substr($Line, 0, 20))]));
        $this->assertFalse(@IPS_GetObjectIDByIdent('bright', $Iid));
        $Intf->ReceiveData(json_encode(['DataID' => '{018EF6B5-AB94-40C6-AA53-46943E824ACF}', 'Buffer' => bin2hex(substr($Line, 20))]));
        $this->assertSame(77, GetValue($this->Id($Iid, 'bright')));
    }

    public function testUnknownValuesAreIgnored(): void
    {
        [$Iid, $Intf] = $this->CreateDevice();
        $this->ReceiveLine($Intf, '{"method":"props","params":{"power":"unknown","music_on":"1"}}');
        $this->assertFalse(@IPS_GetObjectIDByIdent('power', $Iid));
        $this->assertFalse(@IPS_GetObjectIDByIdent('music_on', $Iid));
    }

    public function testActiveModeMapsToColorMode(): void
    {
        [$Iid, $Intf] = $this->CreateDevice(['Mode' => 2]);
        $this->ReceiveLine($Intf, '{"method":"props","params":{"active_mode":1}}');
        $this->assertSame(5, GetValue($this->Id($Iid, 'color_mode')));
        $this->ReceiveLine($Intf, '{"method":"props","params":{"active_mode":0}}');
        $this->assertSame(2, GetValue($this->Id($Iid, 'color_mode')));
        $Presentation = IPS_GetVariable($this->Id($Iid, 'color_mode'))['VariableCustomPresentation'] ?: IPS_GetVariable($this->Id($Iid, 'color_mode'))['VariablePresentation'];
        $this->assertSame(VARIABLE_PRESENTATION_ENUMERATION, $Presentation['PRESENTATION']);
        $Values = array_column(json_decode($Presentation['OPTIONS'], true), 'Value');
        $this->assertSame([2, 5], $Values);
    }

    public function testHueAndSaturationUpdateHSV(): void
    {
        [$Iid, $Intf] = $this->CreateDevice(['HUESlider' => false]);
        $this->ReceiveLine($Intf, '{"method":"props","params":{"hue":120,"sat":50}}');
        $HSV = json_decode(GetValue($this->Id($Iid, 'hsv')), true);
        $this->assertSame(['h' => 120, 's' => 50, 'v' => 100], $HSV);
        $this->assertSame(50, GetValue($this->Id($Iid, 'sat')));
        $this->assertFalse(@IPS_GetObjectIDByIdent('hue', $Iid), 'slider variable without HUESlider');
    }

    public function testBackgroundHueUpdatesBackgroundHSV(): void
    {
        [$Iid, $Intf] = $this->CreateDevice(['HUESlider' => true]);
        $this->ReceiveLine($Intf, '{"method":"props","params":{"bg_hue":200,"bg_sat":30}}');
        $HSV = json_decode(GetValue($this->Id($Iid, 'bg_hsv')), true);
        $this->assertSame(['h' => 200, 's' => 30, 'v' => 100], $HSV);
        $this->assertStringContainsString('bg_hueSlider', GetValue($this->Id($Iid, 'bg_hue')));
        $this->assertFalse(@IPS_GetObjectIDByIdent('hsv', $Iid), 'foreground HSV must not be touched');
    }

    public function testHueKeepsSaturationOfHSV(): void
    {
        [$Iid, $Intf] = $this->CreateDevice(['HUESlider' => false]);
        $this->ReceiveLine($Intf, '{"method":"props","params":{"hue":10}}');
        // Wert wie von der Kachel-Visualisierung (Gleitkomma, s und v in Prozent), Statusvariablen sind schreibgeschützt.
        (new ReflectionMethod($Intf, 'SetValue'))->invoke($Intf, 'hsv', json_encode(['h' => 10.4, 's' => 61.9, 'v' => 98.2]));
        $this->ReceiveLine($Intf, '{"method":"props","params":{"hue":20}}');
        $HSV = json_decode(GetValue($this->Id($Iid, 'hsv')), true);
        $this->assertSame(20, $HSV['h']);
        $this->assertEqualsWithDelta(61.9, $HSV['s'], 0.001);
        $this->assertSame(100, $HSV['v']);
    }

    public function testHSVActionFromVisualization(): void
    {
        [, $Intf] = $this->CreateDevice(['HUESlider' => false]);
        $Decode = new ReflectionMethod($Intf, 'DecodeHSVValue');
        $this->assertSame([62, 62, 98], $Decode->invoke($Intf, '{"h":61.53764578576783,"s":61.8896257402005,"v":98.20657062306826}'));
        $this->assertSame([0, 100, 100], $Decode->invoke($Intf, '{"h":360,"s":100,"v":100}'));
        $this->assertSame([10, 20, null], $Decode->invoke($Intf, '{"h":10,"s":20}'));
    }

    public function testHSVValueFollowsBrightness(): void
    {
        [$Iid, $Intf] = $this->CreateDevice();
        $this->ReceiveLine($Intf, '{"method":"props","params":{"bright":40,"hue":120,"sat":50}}');
        $this->assertSame(['h' => 120, 's' => 50, 'v' => 40], json_decode(GetValue($this->Id($Iid, 'hsv')), true));
        $this->ReceiveLine($Intf, '{"method":"props","params":{"bright":75}}');
        $this->assertSame(75, json_decode(GetValue($this->Id($Iid, 'hsv')), true)['v']);
    }

    public function testSleepTimerVariable(): void
    {
        [$Iid, $Intf] = $this->CreateDevice();
        $this->ReceiveLine($Intf, '{"method":"props","params":{"delayoff":30}}');
        $Id = $this->Id($Iid, 'delayoff');
        $this->assertSame(30, GetValue($Id));
        $this->assertTrue(HasAction($Id));
        $this->ReceiveLine($Intf, '{"method":"props","params":{"delayoff":0}}');
        $this->assertSame(0, GetValue($Id));
    }

    public function testHUESliderIsOffForNewInstances(): void
    {
        [$Iid, $Intf] = $this->CreateDevice();
        $this->assertFalse(IPS_GetProperty($Iid, 'HUESlider'));
        $this->ReceiveLine($Intf, '{"method":"props","params":{"hue":10}}');
        $this->assertFalse(@IPS_GetObjectIDByIdent('hue', $Iid));
    }

    public function testAdjustRangeCheck(): void
    {
        [, $Intf] = $this->CreateDevice();
        ob_start();
        $this->assertFalse($Intf->AdjustBrightness(101, 500));
        $this->assertSame(sprintf($Intf->Translate('%s out of range.'), 'Percentage') . PHP_EOL, ob_get_clean());
    }

    public function testCommandWithoutParentReportsError(): void
    {
        [, $Intf] = $this->CreateDevice();
        ob_start();
        $Result = $Intf->SetPower(true);
        $Output = ob_get_clean();
        $this->assertFalse($Result);
        $this->assertSame($Intf->Translate('Instance has no active parent.') . PHP_EOL, $Output);
    }

    public function testParameterChecksBeforeSending(): void
    {
        [, $Intf] = $this->CreateDevice();
        $Cases = [
            'SetWhite'      => [fn () => $Intf->SetWhite(1000), 'Temperature'],
            'SetHSV'        => [fn () => $Intf->SetHSV(360, 50), 'HUE'],
            'SetBrightness' => [fn () => $Intf->SetBrightness(101), 'Level'],
            'SetSleep'      => [fn () => $Intf->SetSleep(0), 'Minutes']
        ];
        foreach ($Cases as $Name => [$Call, $Parameter]) {
            ob_start();
            $Result = $Call();
            $Output = ob_get_clean();
            $this->assertFalse($Result, $Name);
            $this->assertSame(sprintf($Intf->Translate('%s out of range.'), $Parameter) . PHP_EOL, $Output, $Name);
        }
        ob_start();
        $this->assertFalse($Intf->SetMode(4));
        $this->assertSame(sprintf($Intf->Translate('Mode %d is not supported.'), 4) . PHP_EOL, ob_get_clean());
        ob_start();
        $this->assertFalse($Intf->StartColorFlow(1, 0, '[1000,1,255]'));
        $this->assertSame(sprintf($Intf->Translate('%s is invalid.'), 'Flow') . PHP_EOL, ob_get_clean());
    }

    /**
     * @return array [InstanzID, Modulobjekt]
     */
    private function CreateDevice(array $Configuration = []): array
    {
        $Iid = IPS_CreateInstance(self::ModuleID);
        if (count($Configuration)) {
            IPS_SetConfiguration($Iid, json_encode($Configuration));
        }
        IPS_ApplyChanges($Iid);
        return [$Iid, IPS\InstanceManager::getInstanceInterface($Iid)];
    }

    private function ReceiveLine(object $Intf, string $Line): void
    {
        $Intf->ReceiveData(json_encode(['DataID' => '{018EF6B5-AB94-40C6-AA53-46943E824ACF}', 'Buffer' => bin2hex($Line . "\r\n")]));
    }

    private function Id(int $Iid, string $Ident): int
    {
        $Id = @IPS_GetObjectIDByIdent($Ident, $Iid);
        $this->assertNotFalse($Id, "variable $Ident missing");
        return $Id;
    }
}
