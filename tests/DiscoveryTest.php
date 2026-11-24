<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/autoload.php';

use PHPUnit\Framework\TestCase;

/**
 * Tests der Zuordnung gefundener Geräte zu vorhandenen Instanzen und des Hinweises zur Geräte-ID.
 */
class DiscoveryTest extends TestCase
{
    private const DiscoveryID = '{7AABB3D2-3D24-4F2C-86CE-A56FB09D188A}';
    private const DeviceID = '{BF5D53BB-EB4E-45C0-8632-5DB4EF49FA9F}';

    private object $Discovery;

    public function setUp(): void
    {
        IPS\Kernel::reset();
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/../library.json');
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/stubs/CoreStubs/library.json');
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/stubs/IOStubs/library.json');
        parent::setUp();
        $Iid = IPS_CreateInstance(self::DiscoveryID);
        IPS_ApplyChanges($Iid);
        $this->Discovery = IPS\InstanceManager::getInstanceInterface($Iid);
    }

    public function testDeviceIdHasPriorityOverHost(): void
    {
        $Instances = [
            10001 => ['Host' => '192.168.1.20', 'DeviceId' => '0x0000000000000001'],
            10002 => ['Host' => '192.168.1.30', 'DeviceId' => '0x0000000000000002']
        ];
        // Gerät 2 hat jetzt die alte IP von Gerät 1
        $this->assertSame(10002, $this->FindInstance($Instances, '0x0000000000000002', ['192.168.1.20']));
        // Gerät 1 mit neuer IP
        $this->assertSame(10001, $this->FindInstance($Instances, '0x0000000000000001', ['192.168.1.99']));
    }

    public function testDeviceIdIsCaseInsensitive(): void
    {
        $Instances = [10001 => ['Host' => '192.168.1.20', 'DeviceId' => '0x00000000004D2B2E4']];
        $this->assertSame(10001, $this->FindInstance($Instances, '0x00000000004d2b2e4', ['192.168.1.21']));
    }

    public function testInstanceWithoutDeviceIdMatchesByHost(): void
    {
        $Instances = [10001 => ['Host' => 'Lamp.local', 'DeviceId' => '']];
        $this->assertSame(10001, $this->FindInstance($Instances, '0x0000000000000001', ['192.168.1.20', 'lamp.local']));
        $this->assertSame(0, $this->FindInstance($Instances, '0x0000000000000001', ['192.168.1.21']));
    }

    public function testInstanceWithOtherDeviceIdDoesNotMatchByHost(): void
    {
        $Instances = [10001 => ['Host' => '192.168.1.20', 'DeviceId' => '0x0000000000000001']];
        $this->assertSame(0, $this->FindInstance($Instances, '0x0000000000000002', ['192.168.1.20']));
    }

    public function testDeviceFormOffersReportedId(): void
    {
        $Iid = IPS_CreateInstance(self::DeviceID);
        IPS_ApplyChanges($Iid);
        $Device = IPS\InstanceManager::getInstanceInterface($Iid);

        $Form = json_decode($Device->GetConfigurationForm(), true);
        $this->assertFalse($Form['elements'][1]['visible'], 'no hint without reported ID');

        $Device->ReportedDeviceId = '0x0000000004d2b2e4';
        $Form = json_decode($Device->GetConfigurationForm(), true);
        $this->assertTrue($Form['elements'][1]['visible']);
        $this->assertStringContainsString("'0x0000000004d2b2e4'", $Form['elements'][1]['items'][1]['onClick']);

        IPS_SetConfiguration($Iid, json_encode(['DeviceId' => '0x0000000004D2B2E4']));
        IPS_ApplyChanges($Iid);
        $Device->ReportedDeviceId = '0x0000000004d2b2e4';
        $Form = json_decode($Device->GetConfigurationForm(), true);
        $this->assertFalse($Form['elements'][1]['visible'], 'no hint when ID matches');
    }

    private function FindInstance(array $Instances, string $DeviceId, array $Hosts): int
    {
        return (new ReflectionMethod($this->Discovery, 'FindInstance'))->invoke($this->Discovery, $Instances, $DeviceId, $Hosts);
    }
}
