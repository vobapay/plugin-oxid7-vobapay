
# VOBAPAY-ZAHLUNGSMODUL FÜR OXID ESHOP
Vobapay-Zahlungsmodul für OXID eShop.

Das Vobapay-Zahlungsmodul ist für die OXID eShop-Versionen 7.0.x in den folgenden Sprachen verfügbar: <b>EN, DE</b>

## Installation

### Option 1: GitHub-Installation
### Voraussetzungen
Software-Anforderungen:
- Installierter OXID eShop >= v7.0.x
- Installierter Composer 2.7.0

##### 1. Laden Sie eine Version von GitHub herunter.
Bitte verwenden Sie die angehängten ZIP-Dateien (vobapay-oxid-X.Y.Z.zip) aus der Liste der verfügbaren Releases: GitHub Releases.
##### 2. Erstellen Sie den Ordner "vobapay" im "vendor"-Verzeichnis der OXID 7-Installation.
##### 3. Erstellen Sie den Ordner "vobapay-oxid" im neuen Ordner "vendor/vobapay" der OXID 7-Installation.
##### 4. Kopieren Sie den Inhalt der heruntergeladenen Version in den neu erstellten Ordner "vobapay-oxid".
##### 5. Importieren Sie nun die Modulkonfiguration.

```
vendor/bin/oe-console oe:module:install vendor/vobapay/vobapay-oxid  
vendor/bin/oe-console oe:module:install-assets  
vendor/bin/oe-console oe:module:activate vobapay  
```

### Option 2: Installation über Composer

Führen Sie die folgenden Schritte im Hauptverzeichnis des Shops aus:
##### 1. Führen Sie den folgenden Befehl aus, um das Zahlungsmodul zu installieren
Aus der ZIP-Datei: Kopieren Sie die ZIP-Datei 'vobapay_module-vobapay-oxid-1.0.0.zip' in das Hauptverzeichnis des Shops.

```
composer config repositories.gclocal artifact ./  
composer require vobapay/vobapay-oxid:1.0.0  
```

Aus dem Git-Repository:
```
composer require vobapay/vobapay-oxid:1.0.0  
```

##### 2. Führen Sie den folgenden Befehl aus, um das Zahlungsmodul zu registrieren
```
vendor/bin/oe-console oe:module:install vendor/vobapay/vobapay-oxid  
vendor/bin/oe-console oe:module:install-assets  
vendor/bin/oe-console oe:module:activate vobapay  
```

### Deinstallation des Moduls
Führen Sie die folgenden Befehle im Hauptverzeichnis des Shops aus:
```
vendor/bin/oe-console oe:module:uninstall vobapay  
composer remove vobapay/vobapay-oxid  
rm source/tmp/*  
```

### Abschließende Schritte
1. Gehen Sie zu „Erweiterungen -> Module -> vobapay payments“, und konfigurieren Sie im Tab „Einstellungen“ den API KEY, die API URL und die Zuordnung des Bestellstatus.
2. Gehen Sie zu „Shop-Einstellungen -> Zahlungsarten“ und aktivieren Sie die vobapay-Zahlungen.
3. Gehen Sie zu „Shop-Einstellungen -> Versandarten“ und konfigurieren Sie die vobapay-Zahlungen in den Versandarten.
