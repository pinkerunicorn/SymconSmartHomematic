# HmIP_WRC6 – HomeMatic IP Wandtaster 6-fach (HmIP-WRC6-230)

Abstraktionsschicht für den **HomeMatic IP Wandtaster 6-fach HmIP-WRC6-230** in IP-Symcon.

## Kanalstruktur des HmIP-WRC6-230

| Kanal | Typ | Funktion | IP-Symcon Parameter |
|-------|-----|----------|-------------------|
| 0 | Gerät | Wartung / Firmware / Systemstatus | – |
| **1** | 🔘 Taster | Taste 1 – kurzer / langer Druck | `PRESS_SHORT`, `PRESS_LONG` |
| **2** | 🔘 Taster | Taste 2 – kurzer / langer Druck | `PRESS_SHORT`, `PRESS_LONG` |
| **3** | 🔘 Taster | Taste 3 – kurzer / langer Druck | `PRESS_SHORT`, `PRESS_LONG` |
| **4** | 🔘 Taster | Taste 4 – kurzer / langer Druck | `PRESS_SHORT`, `PRESS_LONG` |
| **5** | 🔘 Taster | Taste 5 – kurzer / langer Druck | `PRESS_SHORT`, `PRESS_LONG` |
| **6** | 🔘 Taster | Taste 6 – kurzer / langer Druck | `PRESS_SHORT`, `PRESS_LONG` |
| **7** | 💡 LED | Status-LED Taste 1 | `COMBINED_PARAMETER` |
| **8** | 💡 LED | Status-LED Taste 2 | `COMBINED_PARAMETER` |
| **9** | 💡 LED | Status-LED Taste 3 | `COMBINED_PARAMETER` |
| **10** | 💡 LED | Status-LED Taste 4 | `COMBINED_PARAMETER` |
| **11** | 💡 LED | Status-LED Taste 5 | `COMBINED_PARAMETER` |
| **12** | 💡 LED | Status-LED Taste 6 | `COMBINED_PARAMETER` |
| **13** | 🔌 Aktor | Schaltausgang / internes Relais (max. 1380 W) | `STATE` |
| **14** | 🔔 Eingang | Nebenstelleneingang (externer 230V-Taster) | `PRESS_SHORT`, `PRESS_LONG` |

## Öffentliche Funktionen

| Funktion | Beschreibung |
|----------|-------------|
| `WRC6_SetButtonLED($id, $button, $color, $mode, $brightness, $duration)` | LED einer Taste setzen (1–6) |
| `WRC6_SetAllLEDs($id, $color, $mode, $brightness)` | Alle 6 LEDs auf gleiche Farbe setzen |
| `WRC6_ClearAllLEDs($id)` | Alle LEDs ausschalten |
| `WRC6_SetSwitch($id, $state)` | Schaltausgang (Relais, Kanal 13) ein/ausschalten |
| `WRC6_ResetButton($id, $button)` | Button-Zustand zurücksetzen (Timer-intern) |
| `WRC6_ResetAux($id)` | Nebenstelleneingang-Zustand zurücksetzen (Timer-intern) |

## Variablen im Modul

| Variable | Typ | Beschreibung |
|----------|-----|-------------|
| `Button_1` – `Button_6` | Integer | Letzter Tastendruck (0=Nichts, 1=Kurz, 2=Lang) |
| `LED_1` – `LED_6` | Integer | Aktuelle LED-Farbe (steuerbar) |
| `Switch_State` | Boolean | Zustand des Schaltausgangs (steuerbar) |
| `AuxInput_State` | Integer | Letzter Druck am Nebenstelleneingang (0/1/2) |

## Konfiguration

Im Konfigurations-Formular werden vier Bereiche getrennt:

1. **🔘 Tasten (Kanäle 1–6)**: Je Taste eine Bezeichnung + HomeMatic-Instanz
2. **💡 LEDs (Kanäle 7–12)**: Je Taste die zugehörige LED-Instanz
3. **🔌 Schaltausgang (Kanal 13)**: Instanz des internen Relais
4. **🔔 Nebenstelleneingang (Kanal 14)**: Instanz des externen Tastereingangs

## LED-Modi

| Wert | Modus |
|------|-------|
| 1 | Dauerlicht |
| 2–4 | Blinken (langsam / mittel / schnell) |
| 5–7 | Blitzen (langsam / mittel / schnell) |
| 8–10 | Pulsieren (langsam / mittel / schnell) |

## LED-Farben

| Wert | Farbe |
|------|-------|
| 0 | Aus |
| 1 | Blau |
| 2 | Grün |
| 3 | Türkis |
| 4 | Rot |
| 5 | Violett |
| 6 | Gelb |
| 7 | Weiß |
