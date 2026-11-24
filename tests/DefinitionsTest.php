<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/autoload.php';
require_once __DIR__ . '/../libs/YeelightRPC.php';

use PHPUnit\Framework\TestCase;
use Yeelight\Variables;

/**
 * Prüft die Variablendefinitionen gegen die Darstellungen von Symcon, die Übersetzungen und die Aktionen.
 */
class DefinitionsTest extends TestCase
{
    /** Parameter, die jede Darstellung kennt. */
    private const CommonParameters = ['PRESENTATION', 'TEMPLATE'];

    public function testPresentationParameters(): void
    {
        $Presentations = json_decode(file_get_contents(__DIR__ . '/presentations.json'), true);
        foreach (array_keys(Variables::$List) as $Ident) {
            foreach ([0, 1, 2] as $Mode) {
                $Presentation = Variables::getPresentation($Ident, $Mode);
                $Type = Variables::$List[$Ident][Variables::Type];
                $Guid = $Presentation['PRESENTATION'];
                $this->assertArrayHasKey($Guid, $Presentations, "$Ident: unknown presentation");
                $Definition = $Presentations[$Guid];
                $this->assertContains($Type, $Definition['types'], "$Ident: variable type not supported by {$Definition['name']}");
                $Group = $Definition['groups'][(string) $Type] ?? [];
                $Allowed = array_merge(self::CommonParameters, $Definition['parameters'], array_keys($Group));
                foreach ($Presentation as $Parameter => $Value) {
                    $this->assertContains($Parameter, $Allowed, "$Ident (Mode $Mode): parameter $Parameter not known by {$Definition['name']}");
                    if (is_array($Value) && isset($Group[$Parameter])) {
                        foreach ($Value as $Entry) {
                            foreach (array_keys($Entry) as $Field) {
                                $this->assertContains($Field, $Group[$Parameter], "$Ident (Mode $Mode): field $Field in $Parameter not known by {$Definition['name']}");
                            }
                        }
                    }
                }
            }
        }
    }

    public function testVariableTextsAreTranslated(): void
    {
        $Locale = $this->LoadLocale(__DIR__ . '/../YeelightDevice/locale.json');
        foreach (Variables::$List as $Ident => $Variable) {
            $this->assertArrayHasKey($Variable[Variables::Name], $Locale, "$Ident: name not translated");
            foreach ([0, 1, 2] as $Mode) {
                $Presentation = Variables::getPresentation($Ident, $Mode);
                foreach (['PREFIX', 'SUFFIX'] as $Key) {
                    if (($Presentation[$Key] ?? '') !== '') {
                        $this->assertArrayHasKey($Presentation[$Key], $Locale, "$Ident: $Key not translated");
                    }
                }
                foreach ($Presentation['OPTIONS'] ?? [] as $Option) {
                    $this->assertArrayHasKey($Option['Caption'], $Locale, "$Ident: option {$Option['Caption']} not translated");
                }
            }
        }
    }

    public function testVariableNamesAreUnique(): void
    {
        $Names = array_column(Variables::$List, Variables::Name);
        $this->assertSame(count($Names), count(array_unique($Names)));
    }

    public function testTranslateCallsAreTranslated(): void
    {
        foreach (['YeelightDevice', 'YeelightDiscovery'] as $Module) {
            $Locale = $this->LoadLocale(__DIR__ . '/../' . $Module . '/locale.json');
            $Code = file_get_contents(__DIR__ . '/../' . $Module . '/module.php');
            preg_match_all('/->Translate\(\'((?:[^\'\\\\]|\\\\.)*)\'\)/', $Code, $Matches);
            preg_match_all('/->Translate\("((?:[^"\\\\]|\\\\.)*)"\)/', $Code, $MatchesDouble);
            $Texts = $Matches[1];
            foreach ($MatchesDouble[1] as $Text) {
                $Texts[] = stripcslashes($Text);
            }
            $this->assertNotEmpty($Texts);
            foreach ($Texts as $Text) {
                $this->assertArrayHasKey($Text, $Locale, "$Module: '$Text' not translated");
            }
        }
    }

    public function testFormCaptionsAreTranslated(): void
    {
        foreach (['YeelightDevice', 'YeelightDiscovery'] as $Module) {
            $Locale = $this->LoadLocale(__DIR__ . '/../' . $Module . '/locale.json');
            $Form = json_decode(file_get_contents(__DIR__ . '/../' . $Module . '/form.json'), true);
            $this->assertIsArray($Form);
            array_walk_recursive($Form, function ($Value, $Key) use ($Locale, $Module)
            {
                if (($Key === 'caption') && is_string($Value) && ($Value !== '')) {
                    $this->assertArrayHasKey($Value, $Locale, "$Module form: '$Value' not translated");
                }
            });
        }
    }

    public function testActionsCallExistingFunctions(): void
    {
        $Code = file_get_contents(__DIR__ . '/../YeelightDevice/module.php');
        preg_match_all('/public function (\w+)\(/', $Code, $Matches);
        $Functions = array_map('strtolower', $Matches[1]);
        foreach (glob(__DIR__ . '/../actions/*.json') as $File) {
            $Action = json_decode(file_get_contents($File), true);
            $this->assertIsArray($Action, basename($File) . ' is invalid JSON');
            $Script = implode("\n", (array) $Action['action']);
            preg_match_all('/YEELIGHT_(\w+)\(/', $Script, $Calls);
            $this->assertNotEmpty($Calls[1], basename($File));
            foreach ($Calls[1] as $Call) {
                $this->assertContains(strtolower($Call), $Functions, basename($File) . ": YEELIGHT_$Call does not exist");
            }
            $IsBackground = str_ends_with(basename($File, '.json'), 'Bg');
            foreach ($Calls[1] as $Call) {
                $this->assertSame($IsBackground, str_contains($Call, 'Bg'), basename($File) . ": YEELIGHT_$Call does not match foreground/background");
            }
        }
    }

    public function testActionDirectionMatchesName(): void
    {
        foreach (glob(__DIR__ . '/../actions/*.json') as $File) {
            $Name = basename($File, '.json');
            $Script = implode("\n", (array) json_decode(file_get_contents($File), true)['action']);
            if (str_starts_with($Name, 'increase') || str_starts_with($Name, 'increse')) {
                $this->assertStringContainsString('YEELIGHT_Increase', $Script, $Name);
            }
            if (str_starts_with($Name, 'decrease')) {
                $this->assertStringContainsString('YEELIGHT_Decrease', $Script, $Name);
            }
        }
    }

    private function LoadLocale(string $File): array
    {
        $Locale = json_decode(file_get_contents($File), true);
        $this->assertIsArray($Locale, "$File is invalid JSON");
        return $Locale['translations']['de'];
    }
}
