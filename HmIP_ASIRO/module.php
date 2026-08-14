<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/Trait_SmartLog.php';
require_once __DIR__ . '/../libs/Trait_DeviceAvailability.php';

/**
 * HmIP_ASIRO – Abstraktionsschicht für die HomeMatic IP Außensirene (HmIP-ASIR-O / HmIP-ASIR)
 *
 * Ansteuerung über die 4 einzelnen Datenpunkte auf Kanal 3:
 *   1. DURATION_UNIT (0=Sekunden, 1=Minuten, 2=Stunden, 3=Tage)
 *   2. DURATION_VALUE (0 bis 16344)
 *   3. OPTICAL_ALARM_SELECTION (0–7)
 *   4. ACOUSTIC_ALARM_SELECTION (0–17)  <- Triggert das Senden des Funkbefehls!
 */
class HmIP_ASIRO extends IPSModuleStrict
{
    use SmartLog_Trait;
    use DeviceAvailability_Trait;

    // Akustik-Konstanten
    public const ACOUSTIC_OFF                  = 0;
    public const ACOUSTIC_FREQ_RISING          = 1;
    public const ACOUSTIC_FREQ_FALLING         = 2;
    public const ACOUSTIC_FREQ_RISING_FALLING  = 3;
    public const ACOUSTIC_FREQ_LOW_HIGH        = 4;
    public const ACOUSTIC_FREQ_LOW_MID_HIGH    = 5;
    public const ACOUSTIC_FREQ_HIGH_ON_OFF     = 6;
    public const ACOUSTIC_FREQ_HIGH_LONG_OFF   = 7;
    public const ACOUSTIC_FREQ_LOW_HIGH_ON_OFF = 8;
    public const ACOUSTIC_FREQ_LOW_HIGH_LONG   = 9;
    public const ACOUSTIC_BATTERY_LOW          = 10;
    public const ACOUSTIC_DISARMED             = 11;
    public const ACOUSTIC_ARMED_INTERN         = 12;
    public const ACOUSTIC_ARMED_EXTERN         = 13;
    public const ACOUSTIC_DELAYED_INTERN       = 14;
    public const ACOUSTIC_DELAYED_EXTERN       = 15;
    public const ACOUSTIC_ALARM_EVENT          = 16;
    public const ACOUSTIC_ERROR                = 17;

    // Optik-Konstanten
    public const OPTICAL_OFF                   = 0;
    public const OPTICAL_ALT_SLOW              = 1; // Abwechselndes, langsames Blinken
    public const OPTICAL_BOTH_SLOW             = 2; // Gleichzeitiges langsames Blinken
    public const OPTICAL_BOTH_FAST             = 3; // Gleichzeitiges schnelles Blinken
    public const OPTICAL_BOTH_SHORT            = 4; // Gleichzeitiges kurzes Blinken
    public const OPTICAL_CONFIRM_0             = 5; // Bestätigung 0 (lang lang)
    public const OPTICAL_CONFIRM_1             = 6; // Bestätigung 1 (lang kurz)
    public const OPTICAL_CONFIRM_2             = 7; // Bestätigung 2 (lang kurz kurz)

    public function Create(): void
    {
        parent::Create();

        // Konfiguration
        $this->RegisterPropertyInteger('SirenInstanceID', 0);
        $this->RegisterPropertyInteger('DefaultAcoustic', self::ACOUSTIC_ALARM_EVENT);
        $this->RegisterPropertyInteger('DefaultOptical', self::OPTICAL_BOTH_FAST);
        $this->RegisterPropertyInteger('DefaultDuration', 10); // 10 Sekunden Standard

        $this->DA_RegisterAvailability(900);

        // Status-Variablen
        $this->RegisterVariableBoolean('IsActive', 'Sirene aktiv', [
            'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH,
            'ICON'         => 'Alert'
        ], 1);
        $this->EnableAction('IsActive');

        $this->RegisterVariableInteger('AcousticSignal', 'Akustik', [
            'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
            'OPTIONS'      => json_encode([
                ['Value' => 0,  'Caption' => 'Kein Ton',                       'IconActive' => false, 'IconValue' => '', 'Color' => 0x888888],
                ['Value' => 1,  'Caption' => 'Frequenz steigend',             'IconActive' => false, 'IconValue' => '', 'Color' => 0x00AAFF],
                ['Value' => 2,  'Caption' => 'Frequenz fallend',              'IconActive' => false, 'IconValue' => '', 'Color' => 0x0066FF],
                ['Value' => 3,  'Caption' => 'Frequenz steigend/fallend',     'IconActive' => false, 'IconValue' => '', 'Color' => 0x0044CC],
                ['Value' => 4,  'Caption' => 'Frequenz tief/hoch',            'IconActive' => false, 'IconValue' => '', 'Color' => 0x00CCAA],
                ['Value' => 5,  'Caption' => 'Frequenz tief/mittel/hoch',     'IconActive' => false, 'IconValue' => '', 'Color' => 0x004488],
                ['Value' => 6,  'Caption' => 'Frequenz hoch ein/aus',         'IconActive' => false, 'IconValue' => '', 'Color' => 0x0088FF],
                ['Value' => 7,  'Caption' => 'Frequenz hoch ein, lang aus',   'IconActive' => false, 'IconValue' => '', 'Color' => 0x00AACC],
                ['Value' => 8,  'Caption' => 'Frequenz tief ein/aus, hoch ein/aus', 'IconActive' => false, 'IconValue' => '', 'Color' => 0xFF6600],
                ['Value' => 9,  'Caption' => 'Frequenz tief ein/lang, hoch ein/lang', 'IconActive' => false, 'IconValue' => '', 'Color' => 0xFF4400],
                ['Value' => 10, 'Caption' => 'Batterie leer',                 'IconActive' => false, 'IconValue' => '', 'Color' => 0xAAAAAA],
                ['Value' => 11, 'Caption' => 'Unscharf Signal',               'IconActive' => false, 'IconValue' => '', 'Color' => 0x00CC44],
                ['Value' => 12, 'Caption' => 'Intern scharf Signal',          'IconActive' => false, 'IconValue' => '', 'Color' => 0xFFBB00],
                ['Value' => 13, 'Caption' => 'Extern scharf Signal',          'IconActive' => false, 'IconValue' => '', 'Color' => 0xFF0000],
                ['Value' => 14, 'Caption' => 'Verzögert intern scharf',       'IconActive' => false, 'IconValue' => '', 'Color' => 0xFF8800],
                ['Value' => 15, 'Caption' => 'Verzögert extern scharf',       'IconActive' => false, 'IconValue' => '', 'Color' => 0xFF2200],
                ['Value' => 16, 'Caption' => 'Alarm Ereignis',                'IconActive' => false, 'IconValue' => '', 'Color' => 0xFF0000],
                ['Value' => 17, 'Caption' => 'Fehler Signal',                 'IconActive' => false, 'IconValue' => '', 'Color' => 0xCC0000]
            ])
        ], 2);
        $this->EnableAction('AcousticSignal');

        $this->RegisterVariableInteger('OpticalSignal', 'Optik', [
            'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
            'OPTIONS'      => json_encode([
                ['Value' => 0, 'Caption' => 'Kein Licht',                  'IconActive' => false, 'IconValue' => '', 'Color' => 0x888888],
                ['Value' => 1, 'Caption' => 'Abwechselnd langsames Blinken', 'IconActive' => false, 'IconValue' => '', 'Color' => 0xFFAA00],
                ['Value' => 2, 'Caption' => 'Gleichzeitig langsames Blinken','IconActive' => false, 'IconValue' => '', 'Color' => 0xFF7700],
                ['Value' => 3, 'Caption' => 'Gleichzeitig schnelles Blinken', 'IconActive' => false, 'IconValue' => '', 'Color' => 0xFF4400],
                ['Value' => 4, 'Caption' => 'Gleichzeitig kurzes Blinken',  'IconActive' => false, 'IconValue' => '', 'Color' => 0xFF0000],
                ['Value' => 5, 'Caption' => 'Bestätigung 0 (lang lang)',   'IconActive' => false, 'IconValue' => '', 'Color' => 0x00CC44],
                ['Value' => 6, 'Caption' => 'Bestätigung 1 (lang kurz)',   'IconActive' => false, 'IconValue' => '', 'Color' => 0x00AAFF],
                ['Value' => 7, 'Caption' => 'Bestätigung 2 (lang kurz kurz)','IconActive' => false, 'IconValue' => '', 'Color' => 0x0066FF]
            ])
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

        // Validierung
        if ($this->ReadPropertyInteger('SirenInstanceID') <= 0) {
            $this->SetStatus(104);
            return;
        }

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

        $this->SetStatus(102);
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case 'DA_Watchdog':
                $this->DA_HandleWatchdog();
                break;

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
     * @param int $acoustic        Akustisches Signal (0–17, siehe ACOUSTIC_* Konstanten)
     * @param int $optical         Optisches Signal (0–7, siehe OPTICAL_* Konstanten)
     * @param int $durationSeconds Laufzeit in Sekunden (0 = 10s Standard, max 16344)
     * @param int $durationUnit    Einheit (0 = Sekunden, 1 = Minuten, 2 = Stunden, 3 = Tage)
     */
    public function Trigger(int $acoustic = self::ACOUSTIC_ALARM_EVENT, int $optical = self::OPTICAL_BOTH_FAST, int $durationSeconds = 10, int $durationUnit = 0): void
    {
        $instID = $this->ReadPropertyInteger('SirenInstanceID');
        if (!$this->CheckInstance($instID)) {
            return;
        }

        $acoustic     = max(0, min(17, $acoustic));
        $optical      = max(0, min(7, $optical));
        $durationUnit = max(0, min(3, $durationUnit));
        $dv           = max(0, min(16344, $durationSeconds));

        $this->SLogInfo("Trigger: Akustik={$acoustic}, Optik={$optical}, Dauer={$dv} (Einheit={$durationUnit})");
        $this->SendDebug('ASIRO::Trigger', "Unit={$durationUnit}, Value={$dv}, Opt={$optical}, Acou={$acoustic}", 0);

        $this->WriteHMSiren($instID, $durationUnit, $dv, $optical, $acoustic);
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

        $this->SLogInfo('Stop: Sirene ausgeschaltet');
        $this->SendDebug('ASIRO::Stop', 'A=0, O=0', 0);

        $this->WriteHMSiren($instID, 0, 0, 0, 0);
        $this->SetValue('IsActive', false);
    }

    /**
     * Schnelltest: Sirene 5 Sekunden mit Standard-Ton.
     */
    public function Test(): void
    {
        $ac  = $this->ReadPropertyInteger('DefaultAcoustic');
        $opt = $this->ReadPropertyInteger('DefaultOptical');
        $this->Trigger($ac, $opt, 5, 0);
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

    /**
     * Schreibt die 4 einzelnen HM-Datenpunkte in der exakten Reihenfolge:
     * DURATION_UNIT -> DURATION_VALUE -> OPTICAL_ALARM_SELECTION -> ACOUSTIC_ALARM_SELECTION
     */
    private function WriteHMSiren(int $instID, int $unit, int $value, int $optical, int $acoustic): void
    {
        try {
            HM_WriteValueInteger($instID, 'DURATION_UNIT', $unit);
            HM_WriteValueInteger($instID, 'DURATION_VALUE', $value);
            HM_WriteValueInteger($instID, 'OPTICAL_ALARM_SELECTION', $optical);
            HM_WriteValueInteger($instID, 'ACOUSTIC_ALARM_SELECTION', $acoustic); // Triggert die Aussendung!
            $this->DA_SetAvailable(true);
        } catch (\Throwable $e) {
            $this->DA_SetAvailable(false, $e->getMessage());
            $this->SLogError("HM_WriteValueInteger für Sirene fehlgeschlagen: " . $e->getMessage(), "Unit=$unit, Val=$value, Opt=$optical, Acou=$acoustic");
        }
    }

    // =========================================================================
    // Konfigurations-Formular
    // =========================================================================

    public function GetConfigurationForm(): string
    {
        return <<<'EOT'
{
    "status": [
        { "code": 104, "icon": "inactive", "caption": "SirenInstanceID nicht konfiguriert" },
        { "code": 201, "icon": "inactive", "caption": "Gerät nicht erreichbar" },
        { "code": 202, "icon": "inactive", "caption": "Keine Antwort vom Gerät" },
        { "code": 203, "icon": "error", "caption": "Gerätefehler" },
        { "code": 204, "icon": "error", "caption": "Netzwerkfehler" }
    ],
    "elements": [
        {
            "type": "Label",
            "bold": true,
            "caption": "HomeMatic IP Außensirene (HmIP-ASIR-O)"
        },
        {
            "type": "Label",
            "caption": "Wähle die HomeMatic-Instanz von Kanal 3 der Sirene."
        },
        {
            "type": "SelectInstance",
            "name": "SirenInstanceID",
            "caption": "Sirenen-Instanz (Kanal 3)"
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
                        { "caption": "Kein Ton",                               "value": 0 },
                        { "caption": "Frequenz steigend",                     "value": 1 },
                        { "caption": "Frequenz fallend",                      "value": 2 },
                        { "caption": "Frequenz steigend/fallend",             "value": 3 },
                        { "caption": "Frequenz tief/hoch",                    "value": 4 },
                        { "caption": "Frequenz tief/mittel/hoch",             "value": 5 },
                        { "caption": "Frequenz hoch ein/aus",                 "value": 6 },
                        { "caption": "Frequenz hoch ein, lang aus",           "value": 7 },
                        { "caption": "Frequenz tief ein/aus, hoch ein/aus",   "value": 8 },
                        { "caption": "Frequenz tief ein/lang, hoch ein/lang", "value": 9 },
                        { "caption": "Batterie leer",                         "value": 10 },
                        { "caption": "Unscharf Signal",                       "value": 11 },
                        { "caption": "Intern scharf Signal",                  "value": 12 },
                        { "caption": "Extern scharf Signal",                  "value": 13 },
                        { "caption": "Verzögert intern scharf",               "value": 14 },
                        { "caption": "Verzögert extern scharf",               "value": 15 },
                        { "caption": "Alarm Ereignis",                        "value": 16 },
                        { "caption": "Fehler Signal",                         "value": 17 }
                    ]
                },
                {
                    "type": "Select",
                    "name": "DefaultOptical",
                    "caption": "Standard-Optik",
                    "options": [
                        { "caption": "Kein Licht",                        "value": 0 },
                        { "caption": "Abwechselnd langsames Blinken",     "value": 1 },
                        { "caption": "Gleichzeitig langsames Blinken",    "value": 2 },
                        { "caption": "Gleichzeitig schnelles Blinken",    "value": 3 },
                        { "caption": "Gleichzeitig kurzes Blinken",       "value": 4 },
                        { "caption": "Bestätigung 0 (lang lang)",         "value": 5 },
                        { "caption": "Bestätigung 1 (lang kurz)",         "value": 6 },
                        { "caption": "Bestätigung 2 (lang kurz kurz)",    "value": 7 }
                    ]
                },
                {
                    "type": "NumberSpinner",
                    "name": "DefaultDuration",
                    "caption": "Standard-Dauer (Sekunden)",
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
            "type": "Button",
            "caption": "Verbindung testen",
            "onClick": "echo 'SirenInstanceID = ' . $id;",
            "icon": "Network"
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
