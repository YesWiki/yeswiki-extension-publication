# yeswiki-extension-ebook

Crée un pdf à partir d'une sélection de page YesWiki.
La mise en page est rendue possible grâce à [Paged.js](https://gitlab.pagedmedia.org/tools/pagedjs)
([fonctionnement](https://www.pagedmedia.org/paged-js/)).

## Pré-requis

Avoir installé [Chromium/Google Chrome](https://chrome.google.com/) sur
le serveur et connaitre le chemin d'acces vers l'exécutable.

Pour installer Chrome sous Ubuntu/Debian :

```bash
$ curl -sSL https://dl.google.com/linux/direct/google-chrome-stable_current_amd64.deb > google-chrome-stable_current_amd64.deb
$ apt install -y --no-install-recommends ./google-chrome-stable_current_amd64.deb
```

## Configuration

Le fichier de configuration [`wakka.config.php`][wakka-config] accepte
plusieurs paramètres pour ajuster le rendu PDF à votre infrastructure informatique.

| Clé de configuration | Valeur par défaut                  | Utilité
| ---                  | ---                                | ---
| `htmltopdf_path`     | `/usr/bin/google-chrome`           | Indique l'emplacement du programme chargé
| `htmltopdf_options`  | ['windowSize' => ['1440', '780']]  | Options par défaut passées au navigateur embarqué
| `htmltopdf_url`      |                                    | Adresse du serveur YesWiki qui fera le rendu à distance
| `htmltopdf_key`      |                                    | Clé du serveur qui autorise ce wiki à générer des pdf
| `htmltopdf_apikey`   |                                    | A METTRE SUR UN SERVEUR QUI PARTAGE LES FONCTIONS DE GENERATEUR DE PDF défini le mot de passe pour la clé pour les autres wikis

### … avec Chrome/Firefox sur votre serveur

```php
array(
    ...
    'htmltopdf_path' => '/usr/local/bin/chrome',
    'htmltopdf_options' => ['windowSize' => ['1440', '780'], 'noSandbox' => true],
    ...
);
```

### Vous avez un YesWiki paramétré avec le module `yeswiki-extension-ebook`

```php
array(
    ...
    'htmltopdf_url' => 'https://example.org/yeswiki/?PagePrincipale/pdf',
    'htmltopdf_key' => 'motdepasseConfiguré',
    ...
);
```

[wakka-config]: https://yeswiki.net/?DocumentationFichierDeConfiguration
