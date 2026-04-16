# SUMMIT 26 – Website

## Dateien

| Datei | Beschreibung |
|---|---|
| `index.html` | Hauptseite (Long Page) |
| `impressum.html` | Impressum |
| `datenschutz.html` | Datenschutzerklärung |
| `Logo.jpeg` | Logo |

> **Wichtig:** `Logo.jpeg` muss sich im **selben Ordner** wie die HTML-Dateien befinden (also direkt neben `index.html`, `impressum.html` und `datenschutz.html`). Nur dann wird das Logo auf allen Seiten und als Browser-Favicon korrekt angezeigt.

---

## Microsoft Forms einbinden

### Schritt 1 – Formular erstellen

1. Gehe zu [forms.office.com](https://forms.office.com) und melde dich mit deinem Hochschul-Account an
2. Klicke auf **Neues Formular**
3. Füge die gewünschten Felder hinzu, z. B.:
   - Vorname / Nachname (Text, Pflichtfeld)
   - E-Mail-Adresse (Text, Pflichtfeld)
   - Hochschule / Unternehmen (Text)
   - Studiengang / Rolle (Auswahl oder Text)
   - Anmerkungen (Text, optional)

### Schritt 2 – Freigabe-Link kopieren

1. Klicke oben rechts auf **Teilen**
2. Wähle **Link zum Ausfüllen des Formulars**
3. Kopiere den angezeigten Link  
   *(sieht z. B. so aus: `https://forms.office.com/e/XXXXXXXXXX`)*

### Schritt 3 – Link in die Website eintragen

Öffne `index.html` und suche nach:

```
HIER_MICROSOFT_FORMS_LINK_EINSETZEN
```

Ersetze diesen Platzhalter durch deinen kopierten Link:

```html
<a href="https://forms.office.com/e/XXXXXXXXXX" target="_blank" ...>
```

### Schritt 4 – Antworten einsehen

1. Öffne dein Formular auf [forms.office.com](https://forms.office.com)
2. Klicke auf den Tab **Antworten**
3. Dort siehst du alle Anmeldungen in der Übersicht
4. Über **In Excel öffnen** kannst du alle Anmeldungen als Tabelle exportieren

---

## Hinweise

- Der Anmelde-Button öffnet Microsoft Forms in einem **neuen Browser-Tab** (`target="_blank"`)
- Das Formular läuft vollständig über Microsoft – es werden keine Daten auf dem eigenen Server gespeichert
- In der `datenschutz.html` ist Microsoft bereits als Auftragsverarbeiter gemäß DSGVO Art. 28 ausgewiesen
