# HmIP_ASIRO – HomeMatic IP Außensirene (HmIP-ASIR-O)

Abstraktionsschicht für die **HomeMatic IP Außensirene HmIP-ASIR-O** in IP-Symcon.

## Funktionen

| Funktion | Beschreibung |
|----------|-------------|
| `ASIRO_Trigger($id, $acoustic, $optical, $duration)` | Sirene auslösen |
| `ASIRO_Stop($id)` | Sirene sofort stoppen |
| `ASIRO_Test($id)` | Schnelltest für 5 Sekunden |

## Konfiguration

- **Sirenen-Instanz**: HomeMatic-Kanal mit `COMBINED_PARAMETER`
- **Standard-Akustik**: Voreingestelltes akustisches Signal
- **Standard-Optik**: Voreingestelltes optisches Signal
- **Standard-Dauer**: 0 = dauerhaft (bis `ASIRO_Stop` aufgerufen wird)

## Akustik-Werte

| Wert | Signal |
|------|--------|
| 0 | Kein Ton |
| 1 | Frequenz steigend |
| 2 | Frequenz fallend |
| 3 | Frequenz steigend/fallend |
| 4 | Frequenz tief/hoch |
| 5 | Frequenz tief |
| 6 | Frequenz hoch |

## Optik-Werte

| Wert | Signal |
|------|--------|
| 0 | Kein Licht |
| 1 | Blinken |
| 2 | Blitzen |
