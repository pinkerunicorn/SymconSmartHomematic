# HmIP_MP3P – HomeMatic IP Musikgong (HmIP-MP3P)

Abstraktionsschicht für den **HomeMatic IP Musikgong HmIP-MP3P** in IP-Symcon.

## Funktionen

| Funktion | Beschreibung |
|----------|-------------|
| `MP3P_PlaySound($id, $tracks, $volume, $duration)` | Spielt Tracks ab (z.B. `"1"` oder `"1,3,5"`) |
| `MP3P_Stop($id)` | Stoppt die Wiedergabe sofort |
| `MP3P_SetLight($id, $color, $brightness, $duration)` | Schaltet LED ein (0–7 Farben) |
| `MP3P_SetLightOff($id)` | Schaltet LED aus |
| `MP3P_Test($id)` | Schnelltest: Track 1 |

## Konfiguration

- **Sound-Instanz**: HomeMatic-Kanal mit `COMBINED_PARAMETER` für Sound
- **LED-Instanz**: HomeMatic-Kanal mit `COMBINED_PARAMETER` für LED
- **Standard-Lautstärke**: Voreingestellte Lautstärke (0–100%)

## Farb-Konstanten

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
