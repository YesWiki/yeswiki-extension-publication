# yeswiki-extension-ebook
Crée un pdf à partir d'une sélection de page YesWiki.

## Pré-requis
Avoir installé [wkhtmltopdf](https://wkhtmltopdf.org/) sur le serveur et connaitre le chemin d'acces vers l'executable

## Configuration
Si le chemin vers le logiciel wkhtmltopdf est different de `/usr/local/bin/wkhtmltopdf`, vous pouvez rajouter dans le fichier de configuration `wakka.config.php`

```php
array(
    ...
    'wkhtmltopdf_path' => '/chemin/vers/wkhtmltopdf', // SI MAIN SUR LE SERVEUR : lien vers l'executable wkhtmltopdf
    'wkhtmltopdf_url' => 'https://monsitewikiavecungenerateurdepdf.fr/?PagePrincipale/pdf', // SINON : l'url du wiki avec l'option pdf qui marche
    'wkhtmltopdf_key' => 'motdepasse', // clé du serveur qui autorise ce wiki a generer des pdf

    'wkhtmltopdf_apikey' => 'motdepasse', // A METTRE SUR UN SERVEUR QUI PARTAGE LES FONCTIONS DE GENERATEUR DE PDF défini le mot de passe pour la clé pour les autres wikis
    ...
);
```

## Pour générer des newsletters
Créer le formulaire bazar suivant:
```
texte***bf_titre***Titre***60***255*** *** *** ***1***0***
texte***bf_description***Description***60***255*** *** *** ***1***0***
texte***bf_author***Auteur***60***255*** *** *** ***1***0***
textelong***bf_content***Contenu de la newsletter***20***20*** *** ***html***1***0***
```

Trouver son id et utiliser l'action suivante
`{{ebookgenerator outputformat="newsletter" formid="<id du formulaire>"}}`
