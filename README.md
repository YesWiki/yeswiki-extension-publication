# yeswiki-extension-ebook

Crée un pdf à partir d'une sélection de page YesWiki.
La mise en page est rendue possible grâce à [Paged.js](https://gitlab.pagedmedia.org/tools/pagedjs)
([fonctionnement](https://www.pagedmedia.org/paged-js/)).


<table>
  <tr>
    <td>
      <img src="screenshot-edit.png" alt="">
    </td>
    <td>
      <img src="screenshot-preview.png" alt="">
    </td>
  </tr>
  <tr>
    <th scope="col">Assemblage des pages wiki pour constituer un ouvrage</th>
    <th scope="col">Prévisualisaton avant téléchargement</th>
  </tr>
</table>


## Marqueurs YesWiki

Ces marqueurs s'ajoutent dans un contenu de page.


| Marqueur            | Utilité                                       |
| ---                 | ---                                           |
| `{{ebookgenerator}}`| Interface de création de document imprimable  |
| `{{ebooklist}}`     | Liste des documents imprimables               |

## Suffixes de page

Ces suffixes sont ajoutés à chaque page.

| Suffixe       | Utilité                        |
| ---           | ---                            |
| `/pdf`        | Télécharge un document en PDF  |
| `/preview`    | Prévisualisation d'un document |

## Pré-requis

Avoir installé [Chromium/Google Chrome](https://chrome.google.com/) sur
le serveur et connaitre le chemin d'acces vers l'exécutable.

Pour installer Chrome sous Ubuntu/Debian :

```bash
$ curl -sSL https://dl.google.com/linux/direct/google-chrome-stable_current_amd64.deb -o google-chrome.deb
$ apt install -y --no-install-recommends ./google-chrome.deb
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

## Pour générer des newsletters
Créer le formulaire bazar suivant:
```
texte***bf_titre***Titre***60***255*** *** *** ***1***0***
texte***bf_description***Description***60***255*** *** *** ***0***0***
texte***bf_author***Auteur***60***255*** *** *** ***0***0***
textelong***bf_content***Contenu de la newsletter***20***20*** *** ***html***1***0***
```

Trouver son id et utiliser l'action suivante
`{{ebookgenerator outputformat="newsletter" formid="<id du formulaire>"}}`

[wakka-config]: https://yeswiki.net/?DocumentationFichierDeConfiguration
