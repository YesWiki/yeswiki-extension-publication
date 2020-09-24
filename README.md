# Extension YesWiki publication

> Attention — Ceci est une extension de YesWiki. Elle ne fait pas partie du cœur officiellement maintenu de YesWiki.

Cette extension permet de générer des publications au format PDF à partir d'une sélection de fiches bazar ou de pages YesWiki.

Les publications générées peuvent être de type :

- [ebook](#pour-générer-des-ebooks-téléchargeables)
- [newsletter](#pour-générer-des-newsletters)

La mise en page est effectuée par [Paged.js](https://www.pagedjs.org/)
([documentation](https://www.pagedjs.org/documentation/)).

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

## Pré-requis

Avoir installé [Chromium](https://www.chromium.org/Home) **sur
le serveur** et connaitre le chemin d'accès vers l'exécutable.

Pour installer Chrome sous Ubuntu/Debian :

```bash
sudo apt install -y --no-install-recommends chromium
```

## Fonctionnement général

La génération d'une publication se fait en plusieurs étapes.

1. Sélection des éléments constitutifs de la publication.
2. Organisation des éléments constitutifs au sein de la publication.
3. Génération et enregistrement de la publication.
4. Production du PDF.

L'action `{{publicationgenerator}}` prend en charge les étapes 1, 2 et 3.

Le handler `/pdf` prend en charge l'étape 4.

### Pour générer des ebooks téléchargeables

Utiliser l'action `{{publicationgenerator}}`. Aucun paramètre n'est obligatoire.

Chaque ebook généré sera enregistré sous la forme d'une page sur le wiki. Le nom de cette page sera constitué de la valeur du paramètre `ebookpagenameprefix` suivie du titre de l'ebook.

On pourra utilement consulter la section "Action `{{publicationgenerator}}`" ci-après.

### Pour générer des newsletters

Avant de pouvoir générer des newsletters, il faut créer un formulaire bazar suivant avec la structure suivante :

```
texte***bf_titre***Titre***60***255*** *** *** ***1***0***
texte***bf_description***Description***60***255*** *** *** ***0***0***
texte***bf_author***Auteur***60***255*** *** *** ***0***0***
textelong***bf_content***Contenu de la newsletter***20***20*** *** ***html***1***0***
```

Un fois ce formulaire créé, il faut trouver son id et utiliser l'action `{{publicationgenerator}}` avec, au minimum les paramètres suivants :

- `outputformat="newsletter"`
- `formid="<id du formulaire>"`

Soit, au minimum : `{{publicationgenerator outputformat="newsletter" formid="<id du formulaire>"}}`

Chaque newsletter générée sera enregistrée sous la forme d'une fiche bazar du formulaire \<id du formulaire> sur le wiki.

On pourra utilement consulter la section "Action `{{publicationgenerator}}`" ci-après.

## Actions YesWiki

L'extension publication ajoute deux actions à votre wiki.

| Action                    | Utilité                                       |
| ---                       | ---                                           |
| `{{publicationgenerator}}`| Interface de sélection du contenu de la publication et de création du document imprimable (cf. [Action `{{publicationgenerator}}`](#action-publicationgenerator)) |
| `{{ebooklist}}`           | Liste des ebooks générés et imprimables (cf. [Action `{{ebooklist}}`](#action-ebooklist)) |
| `{{bazar2publication}}`   | Liste des ebooks générés et imprimables (cf. [Action `{{bazar2publication}}`](#action-bazar2publication)) |
| `{{blankpage}}`           | Insère une page vide à l'impression. |
| `{{pagebreak}}`           | Crée un saut de page à l'impression. |
| `{{publication-template}}`| Combiné avec `{{bazar2publication}}`, signale l'emplacement réservé à l'injection de contenus. |

Ces actions s'ajoutent, comme toute action YesWiki, dans un contenu de page.

### Action `{{publicationgenerator}}`

Cette action affiche une interface permettant de

- sélectionner, parmi les fiches bazar et pages YesWiki, les éléments constituant la publication ;
- organiser ces différents éléments au sein de la publication ;
- créer le document imprimable résultant.

Les différents paramètres de cette action sont les suivants.

*N.B* — Dans les explications qui suivent, le terme "sélection" désigne la sélection des éléments constitutifs d'une publication.

#### **outputformat**

Détermine le type de publication générée (ebook ou newsletter).

S'il n'est pas précisé, ce paramètre vaut "ebook".

Exemple – pour générer une newsletter il faut donc écrire :

```
{{publicationgenerator outputformat="newsletter"}}
```

#### **formid**

*Paramètre obligatoire dans le cas où on souhaite générer une newsletter.*

Lorsqu'une newsletter est générée, elle est enregistrée sous forme d'une fiche bazar (voir à cet effet la section "Pour générer des newsletters"). Ce paramètre permet de spécifier le numéro identifiant du formulaire bazar en question.

Exemple – pour générer une newsletter avec le formulaire bazar "2", il faut donc écrire :

```
{{publicationgenerator outputformat="newsletter" formid="2"}}
```

#### **pagestart**

Nom de la page à utiliser comme page d'introduction de la publication.

Exemple :

```
{{publicationgenerator pagestart="MaPageWiki"}}
```

#### **pageend**

Nom de la page à utiliser comme page de fin de la publication.

Exemple :

```
{{publicationgenerator pageend="MaPageWiki"}}
```

#### **ebookpagenameprefix**

*Paramètre utilisé uniquement dans le cas d'un ebook.*

Lorsqu'un ebook est généré, il est enregistré sous forme d'une page YesWiki (voir à cet effet la section "Pour générer des ebooks"). Ce paramètre permet de spécifier le préfixe automatiquement ajouté en début du nom de la page ainsi créée.

S'il n'est pas précisé, ce paramètre vaut "Ebook".

Exemple – pour générer un ebook avec le préfixe "MesEDoc", il faut donc écrire :

```
{{publicationgenerator outputformat="ebook" ebookpagenameprefix="MesEDoc"}}
```


#### **addinstalledpage**

Certaines pages de YesWiki ont un statut un peu particulier. Il s'agit des pages créées par défaut lor de l'installation du wiki. Parmi ces pages on trouve, les pages de menu, les entêtes, pieds de pages, mais également PagePincipale.

Si ce paramètre n'est pas présent, ou s'il est vide, ou s'il est égal à 0, lors de la sélection, on ne propose pas ces pages.

Toute autre valeur de ce paramètre, fera apparaître ces pages.

Exemple – pour faire apparaître les pages créées lors del 'installation du wiki, il peut écrire :

```
{{publicationgenerator addinstalledpage="1"}}
```

#### **coverimage**

*Paramètre utilisé uniquement dans le cas d'un ebook.*

Ce paramètre contient l'adressse de l'image de couverture utilisée en 1re page de couverture des ebooks générés.

Si ce paramètre n'est pas renseigné ou est vide, l'utilisateur pourra, lors de la sélection en vue d'un ebook, choisir une image de couverture.

Exemple  :

```
{{publicationgenerator outputformat="ebook" coverimage="monImage.jpg"}}
```

#### **title**

Titre par défaut des publications à générer.

Exemple  :

```
{{publicationgenerator title="Guerre et paix"}}
```

#### **desc**

Description par défaut des publications à générer.

Exemple  :

```
{{publicationgenerator desc="Les nouveautés du mois dernier"}}
```

#### **author**

Auteur·ices par défaut des publications à générer.

Exemple  :

```
{{publicationgenerator title="George Sand"}}
```

#### **chapterpages**

Liste des noms des pages YesWiki à utiliser comme chapitre des publications à générer.

Dans la liste, les noms doivent être séparés par des virgules.

Si ce paramètre est renseigné, les pages ainsi désignées seront proposées par défaut dans la publication lors de la sélection.

L'utilisateur pourra, lors de la sélection, choisir les pages qu'il souhaite mettre à la suite de chaque chapitre.

Exemple  :

```
{{publicationgenerator chapterpages="DebutChapitreUn, DebutChapitreDeux, DebutChapitreTrois"}}
```

#### **fields**

Spécifie si les titre, description, auteur·ices, image et chapitres sont modifiables par l'utilisateur.

Ces champs sont gelés à la saisie si sa valeur est `readonly`.

Les paramètres d'impression restent modifiables dans tous les cas.

Exemple :

```
{{publicationgenerator fields="readonly"}}
```

#### **titles**

*S'il est utilisé, ce paramètre doit l'être conjointement au paramètre `groupselector`.*

Liste des noms des groupes dans lesquels seront proposés les éléments (fiches bazar ou pages) lors de la sélection.

Dans la liste, les noms doivent être séparés par des virgules.

#### **groupselector**

*S'il est utilisé, ce paramètre doit l'être conjointement au paramètre `titles`.*

Liste des groupes d'éléments proposés à la sélection.

Dans la liste,

- les groupes doivent être séparés par des virgules ;
- les pages YesWiki seront identifiées par le mot "pages" ;
  pour une page YesWiki, on peut préciser, entre parenthèses et après "pages",
  - une liste de mots clefs associés à ces pages,
  - les critères doivent être séparés par des "|" ;
- un formulaire bazar est identifié par son numéro identifiant (exemple : "1"),
  pour un formulaire, on peut préciser, entre parenthèses et après son numéro,
  - une liste de critères de sélection dans ce formulaire,
  - les critères doivent être séparés par des "|".

Exemple – Pour organiser les éléments proposés dans quatre groupes,

- un groupe, nommé "pages wiki", reprenant les pages du wiki ;
- un groupe, nommé "important", reprenant les pages du wiki catégorisées (au moyen d'un mot-clef) comme importantes ;
- un groupe, nommé "recettes de cuisine", reprenant les fiches du formulaire 1 ;
- un groupe, nommé "livres", reprenant les fiches du formulaire 2 dont l'auteur est "Rabelais" ou dont la taille est "long" ;

on écrira :

```
{{publicationgenerator titles="pages wiki, important, recettes de cuisine, livres" groupselector="pages, pages(important), 1, 2(bf_auteur=Rabelais|bf_taille=long)"}}
```

### Action `{{ebooklist}}`

Cette action liste les ebook générés.

#### **ebookpagenameprefix**

Le paramètre `ebookpagenameprefix` précise le préfixe par lequel commencent les noms de pages correspondant à des ebooks.

S'il n'est pas précisé, ce paramètre vaut "Ebook".

Exemple – Pour lister les ebooks dont le préfixe est "MesEDoc", il faut donc écrire :

```
{{ebooklist outputformat="ebook" ebookpagenameprefix="MesEDoc"}}
```

### Action `{{bazar2publication}}`

Cette action exporte les résultats d'une sélection de fiches Bazar en PDF en cliquant sur un bouton. Le téléchargement débute au bout de quelques secondes.
Il n'y a pas d'étape de personnalisation.

L'action est à placer à côté d'une action `{{bazar}}` ou `{{bazarliste}}`.

Tous les paramètres sont facultatifs.

#### **title**

Personnalise le texte affiché sur le bouton.

```
{{bazar2publication title="Imprimer ces résultats"}}
```

#### **icon**

_Par défaut_ : `fa-book`.

Personnalise l'icône affichée (par défaut, `fa-book`).

À choisir parmi le [catalogue Font Awesome](https://fontawesome.com/v4.7.0/icons/).

```
{{bazar2publication icon="fa-cloud-download"}}
```

#### **publication-template**

Par défaut, chaque fiche Bazar démarre sur une nouvelle page.

Cet attribut importe la configuration d'une page Ebook : thème et style de présentation, ainsi que les éléments de configuration saisis dans le formulaire de création.

```
{{bazar2publication publication-template="EbookModelePourBazar"}}
```

Un Ebook modèle se crée comme tout autre publication, à partir d'une [action `{{publicationgenerator}}`](#action-publicationgenerator).

Par défaut, le _contenu_ de la page modèle est remplacé par les fiches Bazar.
L'utilisation de l'action [`{{publication-template}}`](#action-publication-template) dans la page modèle vous donne la liberté de choisir l'emplacement où les fiches Bazar seront insérées.

### Action `{{publication-template}}`

Cette action se place dans une page Ebook dont vous voulez vous servir comme modèle de publication.

Ce modèle de publication s'utilise notamment pour personnaliser un export depuis une liste Bazar à l'aide du [bouton généré par l'action `{{bazar2publication}}`](#action-bazar2publication).

```
{{include page="EbookPageIntro" class="publication-cover"}}
{{include page="EbookRemerciements"}}
{{publication-template}}
{{include page="EbookPageFin" class="publication-end"}}
```

## Handlers (ou suffixes) de page

L'extension publication ajoute deux handlers aux pages de votre wiki.

| handler       | Utilité                        |
| ---           | ---                            |
| `/pdf`        | Télécharge un document en PDF  |
| `/preview`    | Prévisualise un document |

Ces fonctions sont accessibles depuis le sous-menu "partager" du bas de page.


## Configuration serveur (`wakka.config.php`)

Le fichier de configuration [`wakka.config.php`][wakka-config] accepte
plusieurs paramètres pour ajuster le rendu PDF à votre infrastructure informatique.

| Clé de configuration                   | Valeur par défaut                  | Utilité
| ---                                    | ---                                | ---
| `htmltopdf_path`                       | `/usr/bin/chromium`                | Indique l'emplacement du programme chargé
| `htmltopdf_options`                    | ['windowSize' => ['1440', '780'], 'noSandbox' => true]  | Options par défaut passées au navigateur embarqué
| `htmltopdf_service_url`                |                                    | Adresse du serveur YesWiki qui fera le rendu à distance
| `htmltopdf_service_authorized_domains` |                                    | Si votre serveur partage les fonction de générateur de pdf, il faut lui indique les nom de domaines autorisés
| `htmltopdf_cache_life`                 |        300                         | Durée en secondes avant reconstruction du fichier cache pdf

### … avec Chrome/Firefox sur votre serveur

```php
array(
    ...
    'htmltopdf_path' => '/usr/bin/chromium',
    'htmltopdf_options' => ['windowSize' => ['1440', '780'], 'noSandbox' => true],
    ...
);
```

### Vous avez un YesWiki qui est autorisé à utiliser le service pdf de https://example.org/yeswiki

```php
array(
    ...
    'htmltopdf_service_url' => 'https://example.org/yeswiki/?PagePrincipale/pdf',
    ...
);
```

### Vous avez un YesWiki qui est un service pdf pour d'autres wikis

Vous devez indiquer les noms de domaine que vous autorisez :

```php
array(
    ...
    'htmltopdf_service_authorized_domains' => ['example.org', 'youpi.com', 'toto.fr'],
    ...
);
```
