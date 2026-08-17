<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/Trait_SmartLog.php';
require_once __DIR__ . '/../libs/Trait_DeviceAvailability.php';

/**
 * HmIP_WRCR – Abstraktionsschicht für den HomeMatic IP Drehtaster (HmIP-WRCR)
 *
 * Kanal 1 : Taste – PRESS_SHORT, PRESS_LONG, PRESS_LONG_RELEASE, PRESS_LONG_START
 * Kanal 2 : Drehen Rechts – PRESS_SHORT (Langsames Drehen), PRESS_LONG (Schnelles Drehen)
 * Kanal 3 : Drehen Links – PRESS_SHORT (Langsames Drehen), PRESS_LONG (Schnelles Drehen)
 */
class HmIP_WRCR extends IPSModuleStrict
{
    use SmartLog_Trait;
    use DeviceAvailability_Trait;

    public function Create(): void
    {
        parent::Create();

        // Eigenschaften für die Instanzen der Kanäle 1 bis 3
        $this->RegisterPropertyInteger('Channel1_InstID', 0);
        $this->RegisterPropertyInteger('Channel2_InstID', 0);
        $this->RegisterPropertyInteger('Channel3_InstID', 0);

        // Timer zum Zurücksetzen der Boolean-Variablen (500ms)
        $this->RegisterTimer('Reset_Button_Short', 0, "WRCR_ResetAction(\$_IPS['TARGET'], 'Button_Short');");
        $this->RegisterTimer('Reset_Button_Long', 0, "WRCR_ResetAction(\$_IPS['TARGET'], 'Button_Long');");
        $this->RegisterTimer('Reset_TurnRight_Slow', 0, "WRCR_ResetAction(\$_IPS['TARGET'], 'TurnRight_Slow');");
        $this->RegisterTimer('Reset_TurnRight_Fast', 0, "WRCR_ResetAction(\$_IPS['TARGET'], 'TurnRight_Fast');");
        $this->RegisterTimer('Reset_TurnLeft_Slow', 0, "WRCR_ResetAction(\$_IPS['TARGET'], 'TurnLeft_Slow');");
        $this->RegisterTimer('Reset_TurnLeft_Fast', 0, "WRCR_ResetAction(\$_IPS['TARGET'], 'TurnLeft_Fast');");

        $this->DA_RegisterAvailability(900);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        
        $this->DA_ApplyPresentation();

        // Validierung
        if ($this->ReadPropertyInteger('Channel1_InstID') <= 0 && 
            $this->ReadPropertyInteger('Channel2_InstID') <= 0 && 
            $this->ReadPropertyInteger('Channel3_InstID') <= 0) {
            $this->SetStatus(104);
            return;
        }

        // Alle alten Referenzen und Messages aufräumen
        foreach ($this->GetReferenceList() as $refID) {
            $this->UnregisterReference($refID);
        }
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                $this->UnregisterMessage($senderID, $message);
            }
        }

        // Options für die Boolean-Variablen (10-Key Dictionary zwingend nach Regeln)
        $options = json_encode([
            [
                'Value' => false, 
                'Caption' => 'Inaktiv', 
                'IconActive' => false, 
                'IconValue' => '', 
                'ColorActive' => true, 
                'ColorDisplay' => 0x808080, 
                'ColorValue' => 0x808080,
                'ContentColorActive' => false, 
                'ContentColorDisplay' => -1, 
                'ContentColorValue' => -1
            ],
            [
                'Value' => true, 
                'Caption' => 'Aktiv', 
                'IconActive' => true, 
                'IconValue' => 'bolt', 
                'ColorActive' => true, 
                'ColorDisplay' => 0x0088FF, 
                'ColorValue' => 0x0088FF,
                'ContentColorActive' => false, 
                'ContentColorDisplay' => -1, 
                'ContentColorValue' => -1
            ]
        ]);

        $customPresentation = [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => '',
            'OPTIONS' => $options
        ];

        // Kanal 1: Taste
        $ch1 = $this->ReadPropertyInteger('Channel1_InstID');
        if ($ch1 > 1 && @IPS_InstanceExists($ch1)) {
            $this->RegisterReference($ch1);
            $this->SubscribeChannel($ch1);
            $this->RegisterVariableBoolean('Button_Short', 'Taste: Kurzer Druck', $customPresentation, 1);
            $this->RegisterVariableBoolean('Button_Long', 'Taste: Langer Druck', $customPresentation, 2);
        } else {
            $this->UnregisterVariable('Button_Short');
            $this->UnregisterVariable('Button_Long');
        }

        // Kanal 2: Drehen Rechts
        $ch2 = $this->ReadPropertyInteger('Channel2_InstID');
        if ($ch2 > 1 && @IPS_InstanceExists($ch2)) {
            $this->RegisterReference($ch2);
            $this->SubscribeChannel($ch2);
            $this->RegisterVariableBoolean('TurnRight_Slow', 'Drehen Rechts: Langsam', $customPresentation, 3);
            $this->RegisterVariableBoolean('TurnRight_Fast', 'Drehen Rechts: Schnell', $customPresentation, 4);
        } else {
            $this->UnregisterVariable('TurnRight_Slow');
            $this->UnregisterVariable('TurnRight_Fast');
        }

        // Kanal 3: Drehen Links
        $ch3 = $this->ReadPropertyInteger('Channel3_InstID');
        if ($ch3 > 1 && @IPS_InstanceExists($ch3)) {
            $this->RegisterReference($ch3);
            $this->SubscribeChannel($ch3);
            $this->RegisterVariableBoolean('TurnLeft_Slow', 'Drehen Links: Langsam', $customPresentation, 5);
            $this->RegisterVariableBoolean('TurnLeft_Fast', 'Drehen Links: Schnell', $customPresentation, 6);
        } else {
            $this->UnregisterVariable('TurnLeft_Slow');
            $this->UnregisterVariable('TurnLeft_Fast');
        }

        $this->SetStatus(102);
    }

    private function SubscribeChannel(int $instID): void
    {
        foreach (IPS_GetChildrenIDs($instID) as $childID) {
            if (!IPS_VariableExists($childID)) continue;
            $ident = IPS_GetObject($childID)['ObjectIdent'] ?? '';
            if (in_array($ident, ['PRESS_SHORT', 'PRESS_LONG'], true)) {
                $this->RegisterMessage($childID, VM_UPDATE);
            }
        }
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        if ($Message !== VM_UPDATE) return;
        $this->DA_SetAvailable(true);

        $value = $Data[0];
        if ($value !== true) return; // Wir reagieren nur auf TRUE bei Tastendrücken

        $ch1 = $this->ReadPropertyInteger('Channel1_InstID');
        $ch2 = $this->ReadPropertyInteger('Channel2_InstID');
        $ch3 = $this->ReadPropertyInteger('Channel3_InstID');

        // Finde heraus, zu welchem Kanal der Sender gehört
        $channel = 0;
        if ($ch1 > 1 && $this->IsChildOf($SenderID, $ch1)) $channel = 1;
        elseif ($ch2 > 1 && $this->IsChildOf($SenderID, $ch2)) $channel = 2;
        elseif ($ch3 > 1 && $this->IsChildOf($SenderID, $ch3)) $channel = 3;

        if ($channel === 0) return;

        $ident = IPS_GetObject($SenderID)['ObjectIdent'] ?? '';

        if ($channel === 1) { // Taste
            if ($ident === 'PRESS_SHORT') {
                $this->TriggerAction('Button_Short', 'Taste: Kurzer Druck');
            } elseif ($ident === 'PRESS_LONG') {
                $this->TriggerAction('Button_Long', 'Taste: Langer Druck');
            }
        } elseif ($channel === 2) { // Drehen Rechts
            if ($ident === 'PRESS_SHORT') {
                $this->TriggerAction('TurnRight_Slow', 'Drehen Rechts: Langsam');
            } elseif ($ident === 'PRESS_LONG') {
                $this->TriggerAction('TurnRight_Fast', 'Drehen Rechts: Schnell');
            }
        } elseif ($channel === 3) { // Drehen Links
            if ($ident === 'PRESS_SHORT') {
                $this->TriggerAction('TurnLeft_Slow', 'Drehen Links: Langsam');
            } elseif ($ident === 'PRESS_LONG') {
                $this->TriggerAction('TurnLeft_Fast', 'Drehen Links: Schnell');
            }
        }
    }

    private function IsChildOf(int $childID, int $parentID): bool
    {
        return @IPS_GetObject($childID)['ParentID'] === $parentID;
    }

    private function TriggerAction(string $varIdent, string $logMsg): void
    {
        $this->SendDebug('WRCR::Action', $logMsg, 0);
        $this->SLogInfo($logMsg);
        
        $this->SetValue($varIdent, true);
        
        $timerIdent = 'Reset_' . $varIdent;
        $this->SetTimerInterval($timerIdent, 500);
    }

    public function ResetAction(string $varIdent): void
    {
        $timerIdent = 'Reset_' . $varIdent;
        $this->SetTimerInterval($timerIdent, 0);
        if (@IPS_GetObjectIDByIdent($varIdent, $this->InstanceID)) {
            $this->SetValue($varIdent, false);
        }
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        if ($Ident === 'DA_Watchdog') {
            $this->DA_HandleWatchdog();
            return;
        }

        throw new \RuntimeException("Unbekannter Ident: {$Ident}");
    }

    public function GetConfigurationForm(): string
    {
        $formData = [
            'status' => [
                [ 'code' => 104, 'icon' => 'inactive', 'caption' => 'Keine Instanzen konfiguriert' ]
            ],
            'elements' => [
                ['type' => 'Label', 'bold' => true, 'caption' => 'HomeMatic IP Drehtaster (HmIP-WRCR)'],
                ['type' => 'Label', 'caption' => 'Bitte weise den jeweiligen Kanälen die entsprechenden CCU-Instanzen zu.'],
                
                [
                    'type' => 'SelectInstance', 
                    'name' => 'Channel1_InstID', 
                    'caption' => 'Kanal 1 (Taste - PRESS_SHORT/LONG)'
                ],
                [
                    'type' => 'SelectInstance', 
                    'name' => 'Channel2_InstID', 
                    'caption' => 'Kanal 2 (Drehen Rechts - Langsam/Schnell)'
                ],
                [
                    'type' => 'SelectInstance', 
                    'name' => 'Channel3_InstID', 
                    'caption' => 'Kanal 3 (Drehen Links - Langsam/Schnell)'
                ]
            ]
        ];

        return json_encode($formData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
