<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/Trait_SmartLog.php';

/**
 * HmIP_ASIRO – Abstraktionsschicht für die HomeMatic IP Außensirene (HmIP-ASIR-O)
 *
 * Die Sirene wird über HM_WriteValueString mit COMBINED_PARAMETER angesteuert.
 *
 * COMBINED_PARAMETER Felder:
 *   O  = Optisches Signal (0=Kein Licht, 1=Blinken, 2=Blitzen)
 *   A  = Akustisches Signal (0=Kein Ton, 1=Frequenz steigend, 2=fallend,
 *         3=steigend/fallend, 4=tief/hoch, 5=tief, 6=hoch)
 *   DV = Dauer-Wert
 *   DU = Dauer-Einheit (0=s, 1=min, 2=h → 2=dauerhaft mit DV=31)
 */
class HmIP_ASIRO extends IPSModuleStrict
{
    use SmartLog_Trait;

    // Akustik-Konstanten
    public const ACOUSTIC_OFF             = 0;
    public const ACOUSTIC_FREQ_RISING     = 1;
    public const ACOUSTIC_FREQ_FALLING    = 2;
    public const ACOUSTIC_FREQ_ALTERNATING = 3;
    public const ACOUSTIC_FREQ_LOW_HIGH   = 4;
    public const ACOUSTIC_FREQ_LOW        = 5;
    public const ACOUSTIC_FREQ_HIGH       = 6;

    // Optik-Konstanten
    public const OPTICAL_OFF   = 0;
    public const OPTICAL_BLINK = 1;
    public const OPTICAL_FLASH = 2;

    public function Create(): void
    {
        parent::Create();

        // Konfiguration
        $this->RegisterPropertyInteger('SirenInstanceID', 0);
        $this->RegisterPropertyInteger('DefaultAcoustic', self::ACOUSTIC_FREQ_ALTERNATING);
        $this->RegisterPropertyInteger('DefaultOptical', self::OPTICAL_FLASH);
        $this->RegisterPropertyInteger('DefaultDuration', 0); // 0 = dauerhaft

        // Profile anlegen
        if (!IPS_VariableProfileExists('HmIP.ASIRO.Acoustic')) {
            IPS_CreateVariableProfile('HmIP.ASIRO.Acoustic', 1);
            IPS_SetVariableProfileAssociation('HmIP.ASIRO.Acoustic', 0, 'Kein Ton',              '', 0x888888);
            IPS_SetVariableProfileAssociation('HmIP.ASIRO.Acoustic', 1, 'Freq. steigend',        '', 0x00AAFF);
            IPS_SetVariableProfileAssociation('HmIP.ASIRO.Acoustic', 2, 'Freq. fallend',         '', 0x0066FF);
            IPS_SetVariableProfileAssociation('HmIP.ASIRO.Acoustic', 3, 'Freq. steig./fallend',  '', 0x0044CC);
            IPS_SetVariableProfileAssociation('HmIP.ASIRO.Acoustic', 4, 'Freq. tief/hoch',       '', 0x00CCAA);
            IPS_SetVariableProfileAssociation('HmIP.ASIRO.Acoustic', 5, 'Freq. tief',            '', 0x004488);
            IPS_SetVariableProfileAssociation('HmIP.ASIRO.Acoustic', 6, 'Freq. hoch',            '', 0x0088FF);
        }

        if (!IPS_VariableProfileExists('HmIP.ASIRO.Optical')) {
            IPS_CreateVariableProfile('HmIP.ASIRO.Optical', 1);
            IPS_SetVariableProfileAssociation('HmIP.ASIRO.Optical', 0, 'Kein Licht', '', 0x888888);
            IPS_SetVariableProfileAssociation('HmIP.ASIRO.Optical', 1, 'Blinken',    '', 0xFFAA00);
            IPS_SetVariableProfileAssociation('HmIP.ASIRO.Optical', 2, 'Blitzen',    '', 0xFF4400);
        }

        // Status-Variablen
        $this->RegisterVariableBoolean('IsActive', 'Sirene aktiv', [
            'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH,
            'ICON'         => 'Alert'
        ], 1);
        $this->EnableAction('IsActive');

        $this->RegisterVariableInteger('AcousticSignal', 'Akustik', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'PROFILE'      => 'HmIP.ASIRO.Acoustic',
        ], 2);
        $this->EnableAction('AcousticSignal');

        $this->RegisterVariableInteger('OpticalSignal', 'Optik', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'PROFILE'      => 'HmIP.ASIRO.Optical',
        ], 3);
        $this->EnableAction('OpticalSignal');

        $this->RegisterVariableInteger('Duration', 'Dauer (Sekunden)', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON'         => 'Clock',
            'SUFFIX'       => ' s'
        ], 4);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        foreach ($this->GetReferenceList() as $refID) {
            $this->UnregisterReference($refID);
        }

        $instID = $this->ReadPropertyInteger('SirenInstanceID');
        if ($instID > 1 && @IPS_ObjectExists($instID)) {
            $this->RegisterReference($instID);
        }

        // Defaults in Variablen schreiben
        if ($this->GetValue('AcousticSignal') === 0 && $this->ReadPropertyInteger('DefaultAcoustic') > 0) {
            $this->SetValue('AcousticSignal', $this->ReadPropertyInteger('DefaultAcoustic'));
        }
        if ($this->GetValue('OpticalSignal') === 0 && $this->ReadPropertyInteger('DefaultOptical') > 0) {
            $this->SetValue('OpticalSignal', $this->ReadPropertyInteger('DefaultOptical'));
        }
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case 'IsActive':
                if ((bool)$Value) {
                    $ac  = $this->GetValue('AcousticSignal');
                    $opt = $this->GetValue('OpticalSignal');
                    $dur = max(0, (int)$this->GetValue('Duration'));
                    $this->Trigger($ac, $opt, $dur);
                } else {
                    $this->Stop();
                }
                break;

            case 'AcousticSignal':
                $this->SetValue('AcousticSignal', (int)$Value);
                break;

            case 'OpticalSignal':
                $this->SetValue('OpticalSignal', (int)$Value);
                break;

            case 'Duration':
                $this->SetValue('Duration', max(0, (int)$Value));
                break;

            default:
                throw new \RuntimeException("Unbekannter Ident: {$Ident}");
        }
    }

    // =========================================================================
    // Öffentliche API-Funktionen
    // =========================================================================

    /**
     * Löst die Sirene aus.
     *
     * @param int $acoustic        Akustisches Signal (0–6, siehe ACOUSTIC_* Konstanten)
     * @param int $optical         Optisches Signal (0–2, siehe OPTICAL_* Konstanten)
     * @param int $durationSeconds Laufzeit in Sekunden (0 = dauerhaft bis Stop())
     */
    public function Trigger(int $acoustic = self::ACOUSTIC_FREQ_ALTERNATING, int $optical = self::OPTICAL_FLASH, int $durationSeconds = 0): void
    {
        $instID = $this->ReadPropertyInteger('SirenInstanceID');
        if (!$this->CheckInstance($instID)) {
            return;
        }

        $acoustic = max(0, min(6, $acoustic));
        $optical  = max(0, min(2, $optical));
        $dv       = max(0, $durationSeconds);

        // DU=2 mit DV=31 = "dauerhaft" in HomeMatic
        if ($dv === 0) {
            $param = "O={$optical},A={$acoustic},DV=31,DU=2";
        } else {
            $param = "O={$optical},A={$acoustic},DV={$dv},DU=0";
        }

        $this->SLogInfo("Trigger: Akustik={$acoustic}, Optik={$optical}, Dauer={$dv}s");
        $this->SendDebug('ASIRO::Trigger', $param, 0);

        $this->WriteHM($instID, $param);
        $this->SetValue('IsActive', true);
        $this->SetValue('AcousticSignal', $acoustic);
        $this->SetValue('OpticalSignal', $optical);
        $this->SetValue('Duration', $dv);
    }

    /**
     * Stoppt Sirene und Licht sofort.
     */
    public function Stop(): void
    {
        $instID = $this->ReadPropertyInteger('SirenInstanceID');
        if (!$this->CheckInstance($instID)) {
            return;
        }

        $param = 'O=0,A=0,DV=31,DU=2';
        $this->SLogInfo('Stop: Sirene ausgeschaltet');
        $this->SendDebug('ASIRO::Stop', $param, 0);

        $this->WriteHM($instID, $param);
        $this->SetValue('IsActive', false);
    }

    /**
     * Schnelltest: Sirene 5 Sekunden mit Standard-Ton.
     */
    public function Test(): void
    {
        $ac  = $this->ReadPropertyInteger('DefaultAcoustic');
        $opt = $this->ReadPropertyInteger('DefaultOptical');
        $this->Trigger($ac, $opt, 5);
        echo "ASIRO Test: Sirene für 5 Sekunden ausgelöst.";
    }

    // =========================================================================
    // Hilfsfunktionen
    // =========================================================================

    private function CheckInstance(int $instID): bool
    {
        if ($instID <= 1 || !@IPS_InstanceExists($instID)) {
            $this->SLogWarning("Keine gültige Sirenen-Instanz konfiguriert (ID={$instID})");
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
            "caption": "HomeMatic IP Außensirene (HmIP-ASIR-O)"
        },
        {
            "type": "Label",
            "caption": "Wähle die HomeMatic-Instanz mit dem COMBINED_PARAMETER Kanal der Sirene."
        },
        {
            "type": "SelectInstance",
            "name": "SirenInstanceID",
            "caption": "Sirenen-Instanz (COMBINED_PARAMETER Kanal)"
        },
        {
            "type": "Label",
            "bold": true,
            "caption": "Standard-Einstellungen"
        },
        {
            "type": "RowLayout",
            "items": [
                {
                    "type": "Select",
                    "name": "DefaultAcoustic",
                    "caption": "Standard-Akustik",
                    "options": [
                        { "caption": "Kein Ton",             "value": 0 },
                        { "caption": "Freq. steigend",        "value": 1 },
                        { "caption": "Freq. fallend",         "value": 2 },
                        { "caption": "Freq. steig./fallend",  "value": 3 },
                        { "caption": "Freq. tief/hoch",       "value": 4 },
                        { "caption": "Freq. tief",            "value": 5 },
                        { "caption": "Freq. hoch",            "value": 6 }
                    ]
                },
                {
                    "type": "Select",
                    "name": "DefaultOptical",
                    "caption": "Standard-Optik",
                    "options": [
                        { "caption": "Kein Licht", "value": 0 },
                        { "caption": "Blinken",    "value": 1 },
                        { "caption": "Blitzen",    "value": 2 }
                    ]
                },
                {
                    "type": "NumberSpinner",
                    "name": "DefaultDuration",
                    "caption": "Standard-Dauer (0=dauerhaft)",
                    "minimum": 0,
                    "suffix": " s"
                }
            ]
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
                    "caption": "🚨 Sirene 5s Test",
                    "onClick": "ASIRO_Test($id);",
                    "icon": "Alert"
                },
                {
                    "type": "Button",
                    "caption": "⏹ Sirene stoppen",
                    "onClick": "ASIRO_Stop($id);",
                    "icon": "Stop"
                }
            ]
        }
    ]
}
EOT;
    }
}
