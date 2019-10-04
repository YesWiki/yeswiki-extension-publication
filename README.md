# yeswiki-extension-ebook
Crée un pdf à partir d'une sélection de page YesWiki.

## Pré-requis
Avoir installé [wkhtmltopdf](https://wkhtmltopdf.org/) sur le serveur et connaitre le chemin d'acces vers l'executable

## Configuration
Si le chemin vers le logiciel wkhtmltopdf est different de `/usr/local/bin/wkhtmltopdf`, vous pouvez rajouter dans le fichier de configuration `wakka.config.php` 

```php
array(
    ...
    'wkhtmltopdf_path' => '/chemin/vers/wkhtmltopdf'
    ...
); 
```
