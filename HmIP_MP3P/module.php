<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/Trait_SmartLog.php';

/**
 * HmIP_MP3P – Abstraktionsschicht für den HomeMatic IP Musikgong (HmIP-MP3P)
 *
 * Das Gerät wird über HM_WriteValueString mit COMBINED_PARAMETER angesteuert.
 * Diese Klasse bietet typsichere PHP-Funktionen und kapselt die rohen HM-Strings.
 *
 * COMBINED_PARAMETER Felder (Sound):
 *   L   = Lautstärke (0–100)
 *   DU  = Dauer-Einheit (0=s, 1=min, 2=h)
 *   DV  = Dauer-Wert
 *   RTU = Repeat-Zeit-Einheit
 *   RTV = Repeat-Zeit-Wert
 *   R   = Wiederholungen (0=keine)
 *   SL  = Sound-Liste (z.B. "1" oder "1,3,5")
 *
 * COMBINED_PARAMETER Felder (LED):
 *   L   = Helligkeit (0–100)
 *   DV  = Dauer-Wert
 *   DU  = Dauer-Einheit (0=s, 1=min, 2=h)
 *   RTV = Repeat-Zeit-Wert
 *   RTU = Repeat-Zeit-Einheit (1=Dauernd)
 *   C   = Farbe (0=Aus, 1=Blau, 2=Grün, 3=Türkis, 4=Rot, 5=Violett, 6=Gelb, 7=Weiß)
 */
class HmIP_MP3P extends IPSModuleStrict
{
    use SmartLog_Trait;

    // Farb-Konstanten für SetLight
    public const COLOR_OFF     = 0;
    public const COLOR_BLUE    = 1;
    public const COLOR_GREEN   = 2;
    public const COLOR_CYAN    = 3;
    public const COLOR_RED     = 4;
    public const COLOR_VIOLET  = 5;
    public const COLOR_YELLOW  = 6;
    public const COLOR_WHITE   = 7;

    public function Create(): void
    {
        parent::Create();

        // Konfiguration
        $this->RegisterPropertyInteger('SoundInstanceID', 0);
        $this->RegisterPropertyInteger('LightInstanceID', 0);
        $this->RegisterPropertyInteger('DefaultVolume', 80);

        // Status-Variablen (für Tile-UI / Monitoring)
        $this->RegisterVariableBoolean('IsPlaying', 'Spielt gerade', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON'         => 'Music'
        ], 1);

        $this->RegisterVariableInteger('Volume', 'Lautstärke', [
            'PRESENTATION' => VARIABLE_PRESENTATION_SLIDER,
            'ICON'         => 'Speaker',
            'SUFFIX'       => ' %',
            'MINVALUE'     => 0,
            'MAXVALUE'     => 100
        ], 2);
        $this->EnableAction('Volume');

        $this->RegisterVariableString('CurrentTrack', 'Aktueller Track', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON'         => 'Music'
        ], 3);

        $this->RegisterVariableBoolean('LightActive', 'LED aktiv', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON'         => 'Bulb'
        ], 4);

        if (!IPS_VariableProfileExists('HmIP.MP3P.Color')) {
            IPS_CreateVariableProfile('HmIP.MP3P.Color', 1);
            IPS_SetVariableProfileAssociation('HmIP.MP3P.Color', 0, 'Aus',      '',  0x000000);
            IPS_SetVariableProfileAssociation('HmIP.MP3P.Color', 1, 'Blau',     '', 0x0000FF);
            IPS_SetVariableProfileAssociation('HmIP.MP3P.Color', 2, 'Grün',     '', 0x00FF00);
            IPS_SetVariableProfileAssociation('HmIP.MP3P.Color', 3, 'Türkis',   '', 0x00FFFF);
            IPS_SetVariableProfileAssociation('HmIP.MP3P.Color', 4, 'Rot',      '', 0xFF0000);
            IPS_SetVariableProfileAssociation('HmIP.MP3P.Color', 5, 'Violett',  '', 0x8800FF);
            IPS_SetVariableProfileAssociation('HmIP.MP3P.Color', 6, 'Gelb',     '', 0xFFFF00);
            IPS_SetVariableProfileAssociation('HmIP.MP3P.Color', 7, 'Weiß',     '', 0xFFFFFF);
        }

        $this->RegisterVariableInteger('LightColor', 'LED Farbe', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'PROFILE'      => 'HmIP.MP3P.Color',
        ], 5);
        $this->EnableAction('LightColor');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        foreach ($this->GetReferenceList() as $refID) {
            $this->UnregisterReference($refID);
        }

        $soundInst = $this->ReadPropertyInteger('SoundInstanceID');
        if ($soundInst > 1 && @IPS_ObjectExists($soundInst)) {
            $this->RegisterReference($soundInst);
        }

        $lightInst = $this->ReadPropertyInteger('LightInstanceID');
        if ($lightInst > 1 && @IPS_ObjectExists($lightInst)) {
            $this->RegisterReference($lightInst);
        }

        // Lautstärke-Variable mit Default befüllen falls leer
        if ($this->GetValue('Volume') === 0) {
            $this->SetValue('Volume', $this->ReadPropertyInteger('DefaultVolume'));
        }

        $isPlayingOptions = json_encode([
            ['Value' => false, 'Caption' => 'Gestoppt', 'IconValue' => 'Music', 'IconActive' => false, 'ColorActive' => false, 'ColorDisplay' => -1, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => -1],
            ['Value' => true, 'Caption' => 'Spielt', 'IconValue' => 'Music', 'IconActive' => true, 'ColorActive' => true, 'ColorDisplay' => 0x00CC00, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => 0x00CC00]
        ]);
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('IsPlaying'), [
            'PRESENTATION' => '{3319437D-7CDE-699D-750A-3C6A3841FA75}',
            'ICON' => 'Music',
            'COLOR' => -1,
            'CONTENT_COLOR' => -1,
            'DISPLAY_TYPE' => 0,
            'PREVIEW_STYLE' => 1,
            'SHOW_PREVIEW' => true,
            'OPTIONS' => $isPlayingOptions
        ]);

        $lightActiveOptions = json_encode([
            ['Value' => false, 'Caption' => 'Aus', 'IconValue' => 'Bulb', 'IconActive' => false, 'ColorActive' => false, 'ColorDisplay' => -1, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => -1],
            ['Value' => true, 'Caption' => 'An', 'IconValue' => 'Bulb', 'IconActive' => true, 'ColorActive' => true, 'ColorDisplay' => 0xFFCC00, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => 0xFFCC00]
        ]);
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('LightActive'), [
            'PRESENTATION' => '{3319437D-7CDE-699D-750A-3C6A3841FA75}',
            'ICON' => 'Bulb',
            'COLOR' => -1,
            'CONTENT_COLOR' => -1,
            'DISPLAY_TYPE' => 0,
            'PREVIEW_STYLE' => 1,
            'SHOW_PREVIEW' => true,
            'OPTIONS' => $lightActiveOptions
        ]);
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case 'Volume':
                $vol = max(0, min(100, (int)$Value));
                $this->SetValue('Volume', $vol);
                break;

            case 'LightColor':
                $color = (int)$Value;
                $this->SetValue('LightColor', $color);
                if ($color === self::COLOR_OFF) {
                    $this->SetLightOff();
                } else {
                    $this->SetLight($color, 100, 0);
                }
                break;

            default:
                throw new \RuntimeException("Unbekannter Ident: $Ident");
        }
    }

    // =========================================================================
    // Öffentliche API-Funktionen
    // =========================================================================

    /**
     * Spielt einen oder mehrere Tracks ab.
     *
     * @param string $tracks          Komma-getrennte Track-Nummern (z.B. "1" oder "1,3,5")
     * @param int    $volume          Lautstärke 0–100 (Standard: Modul-Default)
     * @param int    $durationSeconds Abspieldauer in Sekunden (0 = einmal abspielen)
     */
    public function PlaySound(string $tracks = '1', int $volume = -1, int $durationSeconds = 0): void
    {
        $instID = $this->ReadPropertyInteger('SoundInstanceID');
        if (!$this->CheckInstance($instID, 'Sound')) {
            return;
        }

        if ($volume < 0) {
            $volume = $this->GetValue('Volume');
        }
        $volume = max(0, min(100, $volume));

        $dv  = max(0, $durationSeconds);
        $rep = ($dv > 0) ? 0 : 0; // 0 = einmal abspielen

        $param = "L={$volume},DU=0,DV={$dv},RTU=0,RTV=0,R={$rep},SL={$tracks}";

        $this->SLogInfo("PlaySound: Tracks '{$tracks}', Vol {$volume}%, Dauer {$dv}s");
        $this->SendDebug('MP3P::PlaySound', $param, 0);

        $this->WriteHM($instID, $param);
        $this->SetValue('IsPlaying', true);
        $this->SetValue('CurrentTrack', $tracks);
        $this->SetValue('Volume', $volume);
    }

    /**
     * Stoppt die Wiedergabe sofort.
     */
    public function Stop(): void
    {
        $instID = $this->ReadPropertyInteger('SoundInstanceID');
        if (!$this->CheckInstance($instID, 'Sound')) {
            return;
        }

        // Lautstärke 0 und Dauer 0 = sofortiger Stopp
        $param = 'L=0,DU=0,DV=0,RTU=0,RTV=0,R=0,SL=0';
        $this->SLogInfo('Stop: Wiedergabe gestoppt');
        $this->SendDebug('MP3P::Stop', $param, 0);

        $this->WriteHM($instID, $param);
        $this->SetValue('IsPlaying', false);
        $this->SetValue('CurrentTrack', '');
    }

    /**
     * Schaltet die MP3P-LED ein.
     *
     * @param int $color       Farbe (0–7, siehe COLOR_* Konstanten)
     * @param int $brightness  Helligkeit 0–100
     * @param int $durationSeconds Leuchtet für N Sekunden (0 = dauerhaft)
     */
    public function SetLight(int $color, int $brightness = 100, int $durationSeconds = 0): void
    {
        $instID = $this->ReadPropertyInteger('LightInstanceID');
        if (!$this->CheckInstance($instID, 'Licht')) {
            return;
        }

        $color      = max(0, min(7, $color));
        $brightness = max(0, min(100, $brightness));
        $dv         = max(0, $durationSeconds);

        // RTU=1 = dauerhaft leuchtend (bis manuell gestoppt), wenn DV=0
        $rtu = ($dv === 0) ? 1 : 0;
        $param = "L={$brightness},DV={$dv},DU=0,RTV=0,RTU={$rtu},C={$color}";

        $this->SLogInfo("SetLight: Farbe {$color}, Helligkeit {$brightness}%, Dauer {$dv}s");
        $this->SendDebug('MP3P::SetLight', $param, 0);

        $this->WriteHM($instID, $param);
        $this->SetValue('LightActive', $color > 0);
        $this->SetValue('LightColor', $color);
    }

    /**
     * Schaltet die MP3P-LED aus.
     */
    public function SetLightOff(): void
    {
        $instID = $this->ReadPropertyInteger('LightInstanceID');
        if (!$this->CheckInstance($instID, 'Licht')) {
            return;
        }

        // DV=10 s Ausblenden, C=0 = Aus
        $param = 'L=100,DV=10,DU=0,RTV=0,RTU=1,C=0';
        $this->SLogInfo('SetLightOff: LED ausgeschaltet');
        $this->SendDebug('MP3P::SetLightOff', $param, 0);

        $this->WriteHM($instID, $param);
        $this->SetValue('LightActive', false);
        $this->SetValue('LightColor', self::COLOR_OFF);
    }

    /**
     * Schnelltest: spielt Track 1 mit Default-Lautstärke ab.
     */
    public function Test(): void
    {
        $this->PlaySound('1', $this->GetValue('Volume'), 0);
        echo "MP3P Test: Track 1 abgespielt.";
    }

    // =========================================================================
    // Hilfsfunktionen
    // =========================================================================

    private function CheckInstance(int $instID, string $label): bool
    {
        if ($instID <= 1 || !@IPS_InstanceExists($instID)) {
            $this->SLogWarning("Keine gültige {$label}-Instanz konfiguriert (ID={$instID})");
            return false;
        }
        return true;
    }

    private function WriteHM(int $instID, string $param): void
    {
        try {
            HM_WriteValueString($instID, 'COMBINED_PARAMETER', $param);
        } catch (\Throwable $e) {
            $this->SLogError("HM_WriteValueString fehlgeschlagen: " . $e->getMessage(), $param);
        }
    }

    // =========================================================================
    // Konfigurations-Formular
    // =========================================================================

    public function GetConfigurationForm(): string
    {
        return <<<'EOT'
{
    "elements": [
        {
            "type": "Label",
            "bold": true,
            "caption": "HomeMatic IP Musikgong (HmIP-MP3P)"
        },
        {
            "type": "Label",
            "caption": "Wähle die HomeMatic-Instanzen für Sound und LED. Diese findest du im IP-Symcon Objektbaum unter den HmIP-MP3P Kanälen."
        },
        {
            "type": "RowLayout",
            "items": [
                {
                    "type": "SelectInstance",
                    "name": "SoundInstanceID",
                    "caption": "Sound-Instanz (COMBINED_PARAMETER Kanal)"
                },
                {
                    "type": "SelectInstance",
                    "name": "LightInstanceID",
                    "caption": "LED-Instanz (COMBINED_PARAMETER Kanal)"
                }
            ]
        },
        {
            "type": "NumberSpinner",
            "name": "DefaultVolume",
            "caption": "Standard-Lautstärke (%)",
            "minimum": 0,
            "maximum": 100,
            "suffix": " %"
        }
    ],
    "actions": [
        {
            "type": "Label",
            "bold": true,
            "caption": "Test-Aktionen"
        },
        {
            "type": "RowLayout",
            "items": [
                {
                    "type": "Button",
                    "caption": "▶ Track 1 abspielen",
                    "onClick": "MP3P_PlaySound($id, '1', -1, 0);",
                    "icon": "Play"
                },
                {
                    "type": "Button",
                    "caption": "⏹ Stoppen",
                    "onClick": "MP3P_Stop($id);",
                    "icon": "Stop"
                }
            ]
        },
        {
            "type": "RowLayout",
            "items": [
                {
                    "type": "Button",
                    "caption": "💡 LED Rot an",
                    "onClick": "MP3P_SetLight($id, 4, 100, 0);",
                    "icon": "Bulb"
                },
                {
                    "type": "Button",
                    "caption": "💡 LED Grün an",
                    "onClick": "MP3P_SetLight($id, 2, 100, 0);",
                    "icon": "Bulb"
                },
                {
                    "type": "Button",
                    "caption": "🌑 LED aus",
                    "onClick": "MP3P_SetLightOff($id);",
                    "icon": "Minus"
                }
            ]
        }
    ]
}
EOT;
    }
}
