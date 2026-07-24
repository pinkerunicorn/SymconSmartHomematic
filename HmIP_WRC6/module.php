<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/Trait_SmartLog.php';

/**
 * HmIP_WRC6 – Abstraktionsschicht für den HomeMatic IP Wandtaster 6-fach (HmIP-WRC6-230)
 *
 * ============================================================
 * KANAL-STRUKTUR des HmIP-WRC6-230 in IP-Symcon / CCU3:
 * ============================================================
 *
 * Kanal 0  : Gerät (Wartung / Firmware / Systemstatus)        → kein eigener Kanal nötig
 * Kanal 1  : Taste 1 – PRESS_SHORT, PRESS_LONG                → Button-Instanz
 * Kanal 2  : Taste 2 – PRESS_SHORT, PRESS_LONG                → Button-Instanz
 * Kanal 3  : Taste 3 – PRESS_SHORT, PRESS_LONG                → Button-Instanz
 * Kanal 4  : Taste 4 – PRESS_SHORT, PRESS_LONG                → Button-Instanz
 * Kanal 5  : Taste 5 – PRESS_SHORT, PRESS_LONG                → Button-Instanz
 * Kanal 6  : Taste 6 – PRESS_SHORT, PRESS_LONG                → Button-Instanz
 * Kanal 7  : LED Taste 1 – COMBINED_PARAMETER (Farbe/Modus)   → LED-Instanz
 * Kanal 8  : LED Taste 2 – COMBINED_PARAMETER (Farbe/Modus)   → LED-Instanz
 * Kanal 9  : LED Taste 3 – COMBINED_PARAMETER (Farbe/Modus)   → LED-Instanz
 * Kanal 10 : LED Taste 4 – COMBINED_PARAMETER (Farbe/Modus)   → LED-Instanz
 * Kanal 11 : LED Taste 5 – COMBINED_PARAMETER (Farbe/Modus)   → LED-Instanz
 * Kanal 12 : LED Taste 6 – COMBINED_PARAMETER (Farbe/Modus)   → LED-Instanz
 * Kanal 13 : Schaltausgang (internes Relais, STATE)            → Switch-Instanz
 * Kanal 14 : Nebenstelleneingang (externer 230V-Taster)        → Input-Instanz
 *
 * ============================================================
 * COMBINED_PARAMETER LED-Felder:
 *   L    = Helligkeit (0–100)
 *   DV   = Dauer-Wert
 *   DU   = Dauer-Einheit (0=s, 1=min, 2=h)
 *   RTV  = Repeat-Zeit-Wert
 *   RTU  = Repeat-Zeit-Einheit (0=einmalig, 1=dauernd)
 *   C    = Farbe (0–7)
 *   CB   = Modus (1=Dauer, 2-4=Blinken, 5-7=Blitzen, 8-10=Pulsieren)
 *   RTTOV/RTTOU = Rücksetzzeit (normalerweise 0/3)
 *
 * Button-Druckwerte (ButtonState Integer):
 *   0 = Kein/letzter Druck zurückgesetzt
 *   1 = Kurzer Druck (PRESS_SHORT)
 *   2 = Langer Druck (PRESS_LONG)
 */
class HmIP_WRC6 extends IPSModuleStrict
{
    use SmartLog_Trait;

    // Anzahl der Tasten / LED-Kanäle
    private const NUM_BUTTONS = 6;

    // LED-Farben
    public const COLOR_OFF    = 0;
    public const COLOR_BLUE   = 1;
    public const COLOR_GREEN  = 2;
    public const COLOR_CYAN   = 3;
    public const COLOR_RED    = 4;
    public const COLOR_VIOLET = 5;
    public const COLOR_YELLOW = 6;
    public const COLOR_WHITE  = 7;

    // LED-Modi
    public const MODE_STATIC      = 1;
    public const MODE_BLINK_SLOW  = 2;
    public const MODE_BLINK_MED   = 3;
    public const MODE_BLINK_FAST  = 4;
    public const MODE_FLASH_SLOW  = 5;
    public const MODE_FLASH_MED   = 6;
    public const MODE_FLASH_FAST  = 7;
    public const MODE_PULSE_SLOW  = 8;
    public const MODE_PULSE_MED   = 9;
    public const MODE_PULSE_FAST  = 10;

    public function Create(): void
    {
        parent::Create();

        // --- Tasten-Instanzen (Kanäle 1–6) ---
        for ($i = 1; $i <= self::NUM_BUTTONS; $i++) {
            $this->RegisterPropertyInteger("Button{$i}_InstID", 0);
            $this->RegisterPropertyString("Button{$i}_Label", "Taste {$i}");
        }

        // --- LED-Instanzen (Kanäle 7–12) ---
        for ($i = 1; $i <= self::NUM_BUTTONS; $i++) {
            $this->RegisterPropertyInteger("LED{$i}_InstID", 0);
        }

        // --- Schaltausgang (Kanal 13) ---
        $this->RegisterPropertyInteger('Switch_InstID', 0);

        // --- Nebenstelleneingang (Kanal 14) ---
        $this->RegisterPropertyInteger('AuxInput_InstID', 0);
        $this->RegisterPropertyString('AuxInput_Label', 'Nebenstelleneingang');

        // --- Variable Profile ---
        if (!IPS_VariableProfileExists('HmIP.WRC6.ButtonState')) {
            IPS_CreateVariableProfile('HmIP.WRC6.ButtonState', 1);
            IPS_SetVariableProfileAssociation('HmIP.WRC6.ButtonState', 0, 'Kein Druck', '', 0x888888);
            IPS_SetVariableProfileAssociation('HmIP.WRC6.ButtonState', 1, 'Kurz',       '', 0x00AA00);
            IPS_SetVariableProfileAssociation('HmIP.WRC6.ButtonState', 2, 'Lang',       '', 0xFF6600);
        }

        if (!IPS_VariableProfileExists('HmIP.WRC6.Color')) {
            IPS_CreateVariableProfile('HmIP.WRC6.Color', 1);
            IPS_SetVariableProfileAssociation('HmIP.WRC6.Color', 0, 'Aus',     '', 0x000000);
            IPS_SetVariableProfileAssociation('HmIP.WRC6.Color', 1, 'Blau',    '', 0x0000FF);
            IPS_SetVariableProfileAssociation('HmIP.WRC6.Color', 2, 'Grün',    '', 0x00FF00);
            IPS_SetVariableProfileAssociation('HmIP.WRC6.Color', 3, 'Türkis',  '', 0x00FFFF);
            IPS_SetVariableProfileAssociation('HmIP.WRC6.Color', 4, 'Rot',     '', 0xFF0000);
            IPS_SetVariableProfileAssociation('HmIP.WRC6.Color', 5, 'Violett', '', 0x8800FF);
            IPS_SetVariableProfileAssociation('HmIP.WRC6.Color', 6, 'Gelb',    '', 0xFFFF00);
            IPS_SetVariableProfileAssociation('HmIP.WRC6.Color', 7, 'Weiß',    '', 0xFFFFFF);
        }
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        // Alle alten Referenzen und Messages aufräumen
        foreach ($this->GetReferenceList() as $refID) {
            $this->UnregisterReference($refID);
        }
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                $this->UnregisterMessage($senderID, $message);
            }
        }

        // --- Tasten-Variablen und Subscriptions ---
        for ($i = 1; $i <= self::NUM_BUTTONS; $i++) {
            $btnInstID = $this->ReadPropertyInteger("Button{$i}_InstID");
            $label     = $this->ReadPropertyString("Button{$i}_Label") ?: "Taste {$i}";
            $btnIdent  = "Button_{$i}";

            if ($btnInstID > 1 && @IPS_InstanceExists($btnInstID)) {
                $this->RegisterReference($btnInstID);
                $this->SubscribeButtonChannel($btnInstID, $i);
                $this->MaintainVariable($btnIdent, "🔘 {$label}", 1, 'HmIP.WRC6.ButtonState', $i * 3 - 2, true);
            } else {
                $this->MaintainVariable($btnIdent, "Taste {$i}", 1, 'HmIP.WRC6.ButtonState', $i * 3 - 2, false);
            }

            // LED-Instanz
            $ledInstID = $this->ReadPropertyInteger("LED{$i}_InstID");
            $ledIdent  = "LED_{$i}";

            if ($ledInstID > 1 && @IPS_InstanceExists($ledInstID)) {
                $this->RegisterReference($ledInstID);
                $this->MaintainVariable($ledIdent, "💡 LED {$label}", 1, 'HmIP.WRC6.Color', $i * 3 - 1, true);
                $this->EnableAction($ledIdent);
            } else {
                $this->MaintainVariable($ledIdent, "LED Taste {$i}", 1, 'HmIP.WRC6.Color', $i * 3 - 1, false);
            }
        }

        // --- Schaltausgang ---
        $switchInstID = $this->ReadPropertyInteger('Switch_InstID');
        if ($switchInstID > 1 && @IPS_InstanceExists($switchInstID)) {
            $this->RegisterReference($switchInstID);
            $this->MaintainVariable('Switch_State', '🔌 Schaltausgang (Relais)', 0, [
                'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH,
                'ICON'         => 'Power'
            ], 19, true);
            $this->EnableAction('Switch_State');

            // Statusvariable des Schaltausgangs abonnieren
            foreach (IPS_GetChildrenIDs($switchInstID) as $childID) {
                if (!IPS_VariableExists($childID)) continue;
                $ident = IPS_GetObject($childID)['ObjectIdent'] ?? '';
                if ($ident === 'STATE') {
                    $this->RegisterMessage($childID, VM_UPDATE);
                }
            }
        } else {
            $this->MaintainVariable('Switch_State', 'Schaltausgang', 0, '', 19, false);
        }

        // --- Nebenstelleneingang ---
        $auxInstID = $this->ReadPropertyInteger('AuxInput_InstID');
        $auxLabel  = $this->ReadPropertyString('AuxInput_Label') ?: 'Nebenstelleneingang';

        if ($auxInstID > 1 && @IPS_InstanceExists($auxInstID)) {
            $this->RegisterReference($auxInstID);
            $this->MaintainVariable('AuxInput_State', "🔔 {$auxLabel}", 1, 'HmIP.WRC6.ButtonState', 20, true);

            // PRESS_SHORT / PRESS_LONG des Nebenstelleneingangs abonnieren
            $this->SubscribeButtonChannel($auxInstID, 0); // 0 = Aux-Kanal
        } else {
            $this->MaintainVariable('AuxInput_State', 'Nebenstelleneingang', 1, 'HmIP.WRC6.ButtonState', 20, false);
        }
    }

    /**
     * Abonniert PRESS_SHORT und PRESS_LONG einer HomeMatic-Tasterinstanz.
     */
    private function SubscribeButtonChannel(int $instID, int $buttonIndex): void
    {
        foreach (IPS_GetChildrenIDs($instID) as $childID) {
            if (!IPS_VariableExists($childID)) continue;
            $ident = IPS_GetObject($childID)['ObjectIdent'] ?? '';
            if (in_array($ident, ['PRESS_SHORT', 'PRESS_LONG'], true)) {
                $this->RegisterMessage($childID, VM_UPDATE);
                $this->SendDebug(
                    "WRC6::Subscribe",
                    ($buttonIndex === 0 ? 'Aux' : "Taste {$buttonIndex}") . ": Var {$childID} ({$ident})",
                    0
                );
            }
        }
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        if ($Message !== VM_UPDATE) return;

        $value = $Data[0];

        // --- Schaltausgang STATE-Update ---
        $switchInstID = $this->ReadPropertyInteger('Switch_InstID');
        if ($switchInstID > 1 && @IPS_InstanceExists($switchInstID)) {
            foreach (IPS_GetChildrenIDs($switchInstID) as $childID) {
                if ($childID === $SenderID && (IPS_GetObject($childID)['ObjectIdent'] ?? '') === 'STATE') {
                    $this->SetValue('Switch_State', (bool)$value);
                    return;
                }
            }
        }

        // --- Tasten 1–6 ---
        for ($i = 1; $i <= self::NUM_BUTTONS; $i++) {
            $instID = $this->ReadPropertyInteger("Button{$i}_InstID");
            if ($instID <= 1 || !@IPS_InstanceExists($instID)) continue;

            foreach (IPS_GetChildrenIDs($instID) as $childID) {
                if ($childID !== $SenderID) continue;
                $ident = IPS_GetObject($childID)['ObjectIdent'] ?? '';
                if ($ident === 'PRESS_SHORT' && $value === true) {
                    $this->OnButtonPress($i, 1, false);
                } elseif ($ident === 'PRESS_LONG' && $value === true) {
                    $this->OnButtonPress($i, 2, false);
                }
                return;
            }
        }

        // --- Nebenstelleneingang ---
        $auxInstID = $this->ReadPropertyInteger('AuxInput_InstID');
        if ($auxInstID > 1 && @IPS_InstanceExists($auxInstID)) {
            foreach (IPS_GetChildrenIDs($auxInstID) as $childID) {
                if ($childID !== $SenderID) continue;
                $ident = IPS_GetObject($childID)['ObjectIdent'] ?? '';
                if ($ident === 'PRESS_SHORT' && $value === true) {
                    $this->OnButtonPress(0, 1, true);
                } elseif ($ident === 'PRESS_LONG' && $value === true) {
                    $this->OnButtonPress(0, 2, true);
                }
                return;
            }
        }
    }

    private function OnButtonPress(int $button, int $pressType, bool $isAux): void
    {
        $typeStr = $pressType === 1 ? 'Kurz' : 'Lang';

        if ($isAux) {
            $label   = $this->ReadPropertyString('AuxInput_Label') ?: 'Nebenstelleneingang';
            $varIdent = 'AuxInput_State';
        } else {
            $label   = $this->ReadPropertyString("Button{$button}_Label") ?: "Taste {$button}";
            $varIdent = "Button_{$button}";
        }

        $this->SendDebug('WRC6::Press', "{$label}: {$typeStr}", 0);
        $this->SLogInfo("{$label}: {$typeStr}er Druck");
        $this->SetValue($varIdent, $pressType);

        // Reset nach 500 ms damit nächster Druck erkannt wird
        $timerIdent = $isAux ? 'ResetAux' : "ResetButton_{$button}";
        $timerCode  = $isAux
            ? "WRC6_ResetAux(\$_IPS['TARGET']);"
            : "WRC6_ResetButton(\$_IPS['TARGET'], {$button});";
        $this->RegisterTimer($timerIdent, 500, $timerCode);
    }

    // =========================================================================
    // Öffentliche API-Funktionen
    // =========================================================================

    /**
     * Setzt die LED einer einzelnen Taste (Kanal 7–12 entsprechend Taste 1–6).
     *
     * @param int $button          Tastennummer (1–6)
     * @param int $color           Farbe (0=Aus, 1–7 siehe COLOR_* Konstanten)
     * @param int $mode            Modus (1=Dauer, 2–10 siehe MODE_* Konstanten)
     * @param int $brightness      Helligkeit 0–100
     * @param int $durationSeconds 0 = dauerhaft bis SetButtonLED(..., 0) aufgerufen wird
     */
    public function SetButtonLED(int $button, int $color, int $mode = self::MODE_STATIC, int $brightness = 100, int $durationSeconds = 0): void
    {
        if ($button < 1 || $button > self::NUM_BUTTONS) {
            $this->SLogWarning("SetButtonLED: Ungültige Tastennummer {$button} (erlaubt: 1–6)");
            return;
        }

        $ledInstID = $this->ReadPropertyInteger("LED{$button}_InstID");
        if (!$this->CheckLEDInstance($ledInstID, $button)) return;

        $color      = max(0, min(7, $color));
        $mode       = max(1, min(10, $mode));
        $brightness = max(0, min(100, $brightness));
        $dv         = max(0, $durationSeconds);

        $param = $this->BuildLEDParam($color, $mode, $brightness, $dv);

        $label = $this->ReadPropertyString("Button{$button}_Label") ?: "Taste {$button}";
        $this->SLogInfo("LED Taste {$button} ({$label}): Farbe {$color}, Modus {$mode}, Helligkeit {$brightness}%");
        $this->SendDebug("WRC6::LED{$button}", $param, 0);

        $this->WriteHM($ledInstID, $param);

        // Interne Variable aktualisieren
        $ledIdent = "LED_{$button}";
        if (@IPS_GetObjectIDByIdent($ledIdent, $this->InstanceID)) {
            $this->SetValue($ledIdent, $color);
        }
    }

    /**
     * Setzt alle 6 LEDs auf die gleiche Farbe.
     */
    public function SetAllLEDs(int $color, int $mode = self::MODE_STATIC, int $brightness = 100): void
    {
        for ($i = 1; $i <= self::NUM_BUTTONS; $i++) {
            $ledInstID = $this->ReadPropertyInteger("LED{$i}_InstID");
            if ($ledInstID > 1 && @IPS_InstanceExists($ledInstID)) {
                $this->SetButtonLED($i, $color, $mode, $brightness, 0);
            }
        }
        $this->SLogInfo("SetAllLEDs: Alle LEDs → Farbe {$color}, Modus {$mode}");
    }

    /**
     * Schaltet alle LEDs aus.
     */
    public function ClearAllLEDs(): void
    {
        $this->SetAllLEDs(self::COLOR_OFF, self::MODE_STATIC, 0);
    }

    /**
     * Schaltet den integrierten Schaltausgang (Relais, Kanal 13).
     *
     * @param bool $state true = ein, false = aus
     */
    public function SetSwitch(bool $state): void
    {
        $instID = $this->ReadPropertyInteger('Switch_InstID');
        if ($instID <= 1 || !@IPS_InstanceExists($instID)) {
            $this->SLogWarning('SetSwitch: Keine Schaltausgang-Instanz konfiguriert');
            return;
        }

        $this->SLogInfo('SetSwitch: Schaltausgang → ' . ($state ? 'EIN' : 'AUS'));
        try {
            HM_WriteValueBoolean($instID, 'STATE', $state);
        } catch (\Throwable $e) {
            $this->SLogError('SetSwitch fehlgeschlagen: ' . $e->getMessage());
        }
        $this->SetValue('Switch_State', $state);
    }

    /**
     * Reset eines Button-Zustands nach Timer-Ablauf (intern).
     */
    public function ResetButton(int $button): void
    {
        $this->SetTimerInterval("ResetButton_{$button}", 0);
        $ident = "Button_{$button}";
        if (@IPS_GetObjectIDByIdent($ident, $this->InstanceID)) {
            $this->SetValue($ident, 0);
        }
    }

    /**
     * Reset des Nebenstelleneingang-Zustands nach Timer-Ablauf (intern).
     */
    public function ResetAux(): void
    {
        $this->SetTimerInterval('ResetAux', 0);
        if (@IPS_GetObjectIDByIdent('AuxInput_State', $this->InstanceID)) {
            $this->SetValue('AuxInput_State', 0);
        }
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        if (str_starts_with($Ident, 'LED_')) {
            $button = (int)substr($Ident, 4);
            $color  = (int)$Value;
            $this->SetValue($Ident, $color);
            $this->SetButtonLED($button, $color, self::MODE_STATIC, 100, 0);
            return;
        }

        if ($Ident === 'Switch_State') {
            $this->SetSwitch((bool)$Value);
            return;
        }

        throw new \RuntimeException("Unbekannter Ident: {$Ident}");
    }

    // =========================================================================
    // Hilfsfunktionen
    // =========================================================================

    private function BuildLEDParam(int $color, int $mode, int $brightness, int $dv): string
    {
        if ($color === self::COLOR_OFF) {
            return 'L=0,DV=31,DU=2,RTV=0,RTU=0,C=0,CB=0,RTTOV=0,RTTOU=3';
        }
        return "L={$brightness},DV={$dv},DU=0,RTV=0,RTU=0,C={$color},CB={$mode},RTTOV=0,RTTOU=3";
    }

    private function CheckLEDInstance(int $instID, int $button): bool
    {
        if ($instID <= 1 || !@IPS_InstanceExists($instID)) {
            $this->SLogWarning("Keine LED-Instanz für Taste {$button} konfiguriert (ID={$instID})");
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
        $buttonRows  = [];
        $ledRows     = [];

        for ($i = 1; $i <= self::NUM_BUTTONS; $i++) {
            $buttonRows[] = [
                'type'  => 'RowLayout',
                'items' => [
                    ['type' => 'Label', 'caption' => "Taste {$i} (Kanal {$i}):", 'bold' => false, 'width' => '160px'],
                    ['type' => 'ValidationTextBox', 'name' => "Button{$i}_Label", 'caption' => 'Bezeichnung', 'width' => '180px'],
                    ['type' => 'SelectInstance', 'name' => "Button{$i}_InstID", 'caption' => 'HomeMatic-Instanz (PRESS_SHORT/LONG)']
                ]
            ];
            $ledRows[] = [
                'type'  => 'RowLayout',
                'items' => [
                    ['type' => 'Label', 'caption' => "LED Taste {$i} (Kanal " . (6 + $i) . "):", 'bold' => false, 'width' => '160px'],
                    ['type' => 'SelectInstance', 'name' => "LED{$i}_InstID", 'caption' => 'HomeMatic-Instanz (COMBINED_PARAMETER)']
                ]
            ];
        }

        $formData = [
            'elements' => [
                ['type' => 'Label', 'bold' => true, 'caption' => 'HomeMatic IP Wandtaster 6-fach (HmIP-WRC6-230)'],
                ['type' => 'Label', 'caption' => 'Die 6 Tastenkanäle reagieren auf PRESS_SHORT und PRESS_LONG. Die 6 LED-Kanäle steuern die Status-LEDs neben jeder Taste.'],

                [
                    'type'    => 'ExpansionPanel',
                    'caption' => '🔘 Tasten – Kanäle 1 bis 6 (Taster-Eingänge)',
                    'items'   => array_merge(
                        [['type' => 'Label', 'caption' => 'Weise jeder Taste die entsprechende HomeMatic-Instanz zu. Diese Kanäle liefern PRESS_SHORT und PRESS_LONG Events.']],
                        $buttonRows
                    )
                ],

                [
                    'type'    => 'ExpansionPanel',
                    'caption' => '💡 Status-LEDs – Kanäle 7 bis 12 (LED-Ausgänge)',
                    'items'   => array_merge(
                        [['type' => 'Label', 'caption' => 'Die LED-Instanzen entsprechen den Kanälen 7–12. Jede LED gehört zur Taste mit der gleichen Nummer.']],
                        $ledRows
                    )
                ],

                [
                    'type'    => 'ExpansionPanel',
                    'caption' => '🔌 Schaltausgang – Kanal 13 (internes Relais, max. 1380 W)',
                    'items'   => [
                        ['type' => 'Label', 'caption' => 'Der integrierte Schaltaktor kann direkt eine 230V-Last (z.B. Lampe) schalten. Kanal 13 in der CCU.'],
                        ['type' => 'SelectInstance', 'name' => 'Switch_InstID', 'caption' => 'Schaltausgang-Instanz (STATE)']
                    ]
                ],

                [
                    'type'    => 'ExpansionPanel',
                    'caption' => '🔔 Nebenstelleneingang – Kanal 14 (externer 230V-Taster)',
                    'items'   => [
                        ['type' => 'Label', 'caption' => 'An diesen Eingang kann ein konventioneller 230V-Taster angeschlossen werden. Der Eingang liefert ebenfalls PRESS_SHORT / PRESS_LONG.'],
                        ['type' => 'ValidationTextBox', 'name' => 'AuxInput_Label', 'caption' => 'Bezeichnung des Nebenstellentasters'],
                        ['type' => 'SelectInstance', 'name' => 'AuxInput_InstID', 'caption' => 'Nebenstelleneingang-Instanz']
                    ]
                ]
            ],
            'actions' => [
                ['type' => 'Label', 'bold' => true, 'caption' => 'Test-Aktionen'],
                [
                    'type'  => 'RowLayout',
                    'items' => [
                        ['type' => 'Button', 'caption' => '💡 Alle LEDs Blau',  'onClick' => 'WRC6_SetAllLEDs($id, 1, 1, 50);',   'icon' => 'Bulb'],
                        ['type' => 'Button', 'caption' => '💡 Alle LEDs Grün',  'onClick' => 'WRC6_SetAllLEDs($id, 2, 1, 50);',   'icon' => 'Bulb'],
                        ['type' => 'Button', 'caption' => '💡 Alle LEDs Rot',   'onClick' => 'WRC6_SetAllLEDs($id, 4, 1, 100);',  'icon' => 'Bulb'],
                        ['type' => 'Button', 'caption' => '🌑 Alle LEDs aus',   'onClick' => 'WRC6_ClearAllLEDs($id);',           'icon' => 'Minus'],
                        ['type' => 'Button', 'caption' => '🔌 Relais EIN',      'onClick' => 'WRC6_SetSwitch($id, true);',         'icon' => 'Power'],
                        ['type' => 'Button', 'caption' => '🔌 Relais AUS',      'onClick' => 'WRC6_SetSwitch($id, false);',        'icon' => 'Power']
                    ]
                ]
            ]
        ];

        return json_encode($formData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
