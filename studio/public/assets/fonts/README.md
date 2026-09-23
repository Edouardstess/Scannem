# Polices

Placez ici un fichier `watermark.ttf` si vous souhaitez un filigrane rendu avec
une police précise. Sans ce fichier, `WatermarkService` cherche une police
système courante (DejaVu, Arial), puis retombe sur la police bitmap de GD.

Le filigrane reste lisible dans tous les cas ; seule la finesse du rendu change.
