# Extension Publication

L'extension Publication a été développée principalement par Oncle Tom, alias, [Thomas Parisot](https://github.com/thom4parisot/), et elle est actuellement maintenue par [Jeremy Dufraisse](https://github.com/J9rem/). Elle correspond au besoin, souvent énoncé par les usagers et usagères de YesWiki, de pouvoir générer facilement, à partir des contenus de leur wiki : 
 - des livres / livrets
 - des fanzines
 - des newsletters

Publication permet de gérer 4 étapes qui vous faciliteront la tâche :
 - [Sélectionner les éléments constitutifs de la publication](?id=activer-l39interface-de-conception-de-pdf).
 - Organiser les éléments constitutifs au sein de la publication.
 - Générer et enregistrer la publication.
 - [Produire le PDF](?id=imprimer-des-r%c3%a9sultats-bazar-bazar2publication)

!> A noter que pour cette quatrième étape, chromium devra être [installé](?id=installation-de-chromium) sur votre serveur. Ceci dit, pas de panique si ce n'est pas le cas, des solutions alternatives existent.

## Prise en main

### Générer des publications `{{publicationgenerator}}`

Et non, il ne s'agit pas d'un nom de dinosaure mais de l'action qui vous permettra d'afficher l'interface de génération de pdf sur mesure

![Interface de génération de PDF](images/screenshot-edit.png 'Interface de génération de PDF')

#### Activer l'interface de conception de pdf

Pour activer cette interface, dans la page au sein de laquelle vous souhaiterez l'afficher :
 - Allez dans `composants`
 - Publication
 - Générateur de Publication

Vous obtiendrez l'interface suivante (en cliquant sur options avancées afin d'avoir accès à toutes les possibilités)

!> TODO : ajouter le fichier manquant ci-dessous

![Interface quand on clique sur options avancées](images/missing-file.png 'Interface quand on clique sur options avancées')

#### Eléments paramétrables

- `NB :` soit vous ne mettez rien dans les champs suivants et dans ce cas les usagers devront faire le travail, soit vous remplissez les éléments en permettant (ou pas) au utilisateurices de modifier ces paramètres.
- `Mode de publication` : on ne touche à rien c'est bien un pdf que nous cherchons à générer, l'option newsletter est actuellement dans les choux
- `Sélection des pages` : Si vous ne spécifiez rien, toutes les pages et fiches bazar de votre wiki vont s'afficher... Nous allons donc ici spécifier ce que nous souhaitons afficher :
  - `2` si nous souhaitons afficher **les fiches bazar du formulaire n°2**
  - `2(bf_auteur=Rabelais)` pour **les fiches du formulaire 2 dont l'auteur est Rabelais** pour aller plus loin dans les requêtes, vous pouvez vous servir de la [syntaxe de query](https://yeswiki.net/?DocQuery 'syntaxe de query')
  - `pages` pour **les pages wiki du site**
  - `pages(Rousseau)` pour afficher **les pages wiki qui ont comme tag Rousseau**
    
    A noter deux astuces :
    - vous pouvez combiner les demandes d'affichage de pages, il suffira de les séparer par une virgule : `2(bf_auteur=Rabelais), pages(Rousseau)` par exemple
    - dès que vous commencez à écrire dans ce champ, un autre champ apparaît en dessous "Libellé de chaque groupe de sélection" Cela permettra de rajouter un titre au dessus de chaque paquet de pages afin de s'y retrouver, il suffira de mettre les titres voulus séparés par une virgule : `Auteur Rabelais, Auteur Rousseau`
    
- `Page de démarrage` : Indiquer le nom de la page qui servira de première de couverture
- `Page de fin` : Indiquez le nom de la page qui servira de quatrième de couverture
- `Lecture seule` : Les usagers ne pourront pas modifier les propositions si vous cochez cette case
- `Montrer toutes les pages par défaut dans la liste des pages` : Quand wiki est installé, plein de pages par défaut sont créées. Par défaut, elles ne sont pas visibles dans publication, si vous cochez cette case, elles le seront
- `Image de la page de couverture` : indiquez ici l'adresse web de l'image qui pourra se trouver sur votre wiki ou ailleurs sur le web (clic droit sur l'image puis "copier le lien") (dans ce cas, pas besoin de Page de démarrage)
- `Titre de la page de couverture` : Le titre qui permettra de générer automatiquement la page de couverture (dans ce cas, pas besoin de Page de démarrage)
- `Description sur la page de couverture` : La description qui permettra de générer automatiquement la page de couverture (dans ce cas, pas besoin de Page de démarrage)
- `Auteur` : spécifier l'auteur 
- `Pages servant de délimitation de chapitre` : Dans notre exemple, nous aurons des pages associées à Rabelais et d'autres à Rousseau, si nous appelons ici deux pages wiki qui serviront de tête de chapitres, notre publication sera du plus bel effet : ""PageChapitreRabelais, PageChapitreRousseau"". il suffira de créer ces pages dans votre wiki et le tour sera joué
- `Template` : Si vous travaillez avec un graphiste, il réalisera certainement des templates de restitution des fiches bazar, il aura alors besoin de ce champ

NB. Il existe un paramètre qu'il vous faudra coder à la main si vous souhaitez l'utiliser c'est `ebookpagenameprefix`. Ce paramètre vous permettra de faire commencer le nom de vos ebooks par un préfixe qui sera singulier (sinon par défaut ce sera ebook)
Pour cela, il faudra rajouter dans le code de votre action : `ebookpagenameprefix="MesBouquinsAmoi"`
cela donnera par exemple la syntaxe
`{{publicationgenerator outputformat="ebook" ebookpagenameprefix="MesBouquinsAmoi"}}`

### Imprimer des résultats bazar : `{{bazar2publication}}`

Cette action vous permettra de générer des pdf issus des requêtes réalisées au travers des facettes du formulaire bazar.
Voilà à quoi ressemble l'utilisation de facette (les facettes sont les éléments sur la droite qui permettent de trier):

![Utilisation des facettes sur une carte](images/screenshot-bazar-export.png 'Utilisation des facettes sur une carte')

NB : Les facettes peuvent être utilisées sur un affichage des fiches sous forme de liste, agenda, tableau...

L'ajout de `{{bazar2publication}}` se fait dans la même page que l'affichage des résultats de votre formulaire bazar et génère un bouton proposant la possibilité de générer le fameux pdf. Sur l'exemple ci-dessus c'est le bouton vert "Imprimer les résultats".

#### Ajouter l'action `{{bazar2publication}}` via `composants`

Pour activer cette interface dans la page pertinente :
 - Allez dans `composants`
 - Publication
 - Impression des résultats Bazar

Vous obtiendrez l'interface suivante :

!> TODO : ajouter le fichier manquant ci-dessous

![Interface quand on clique sur options avancées](images/missing-file.png 'Interface quand on clique sur options avancées')

Les paramètres y sont simples :
`Titre` : Le titre qui s'affichera sur votre bouton
`Icône` : L'icône associée
`Classe` : Vous pouvez utiliser toutes les classes associées au bouton pour qu'il soit jaune, gros, sur toute la largeur... cf action bouton dans les composants
`Page modèle` : si vous mettez ici le nom de la page correspondant à un ebook déjà généré avec une page de couverture et une quatrième de couverture, et bien vos contenus s'insèreront au sein de ce modèle pour générer un pdf bien comme il faut.

### Lister les ebooks générés : `{{publicationlist}}`

![Exemple de liste des ebooks générés](images/screenshot-page-index.png 'Exemple de liste des ebooks générés')

#### Ajouter l'action `{{publicationlist}}` via `composants`

Pour activer cette interface, dans la page au sein de laquelle vous souhaiterez l'afficher :
 - Allez dans `composants`
 - Publication
 - Liste des Publications

Cette action n'a qu'un seul paramètre modifiable, **le préfixe de page**.
 - Par défaut tous les ebooks générés au sein de votre wiki seront affichés.
 - Si vous ajoutez un préfixe de page, seuls les ebooks ayant ce préfixe seront affichés.

Enfin, testez cette action connectée comme admin, ou comme simple visiteurice.
Dans le premier cas, vous pourrez :
 - supprimer les ebooks créés
Mais vous ne pourrez pas le faire en étant simple visiteurice.

----

## Configuration de l'extension

Pour fonctionner, l'extension a besoin de :
 - soit avoir le logiciel [`chromium`](https://www.chromium.org/Home) installé sur le serveur
 - soit utiliser un autre YesWiki qui dispose déjà du logiciel [`chromium`](https://www.chromium.org/Home)

### Installation de `chromium`

> **Pré-requis**:
> - disposer d'un accès [`ssh`](https://fr.wikipedia.org/wiki/Secure_Shell) à votre serveur  
> - disposer des droits d'installation en ligne de commande sur ce serveur
> - disposer d'un serveur avec l'extension PHP `ext-sockets` activée

Si le serveur utilise un système d'exploitation de type Ubuntu/Debian , il suffit de taper cette ligne de commande :

```bash
sudo apt install -y --no-install-recommends chromium
```

Une fois l'installation terminée, vous pouvez vérifier l'adresse d'installation grâce à la commande :
```bash
which chromium
```

Ce devrait être `/usr/bin/chromium`. Si avez un autre chemin, notez le bien pour configurer l'extension.

#### Réglages de `chromium`

Certains réglages de `chromium` peuvent être faits.

 1. si le chemin d'accès au logiciel n'est pas `/usr/bin/chromium`, il faut aller taper le bon chemin dans la page [GererConfig](?GererConfig 'Page config :ignore') dans la partie `publication`, paramètre `htmltopdf_path`
 2. les autres paramètres peuvent être mis à jour à la main en utilisant un logiciel d'accès `ftp` au serveur pour modifier le contenu du fichier `wakka.config.php` ou dans la page [GererConfig](?GererConfig 'Page config :ignore') (mais seuls quelques paramètres sont accessibles). C'est le paramètre `htmltopdf_options` qui permet ces réglages. Quelques exemples:
   - `htmltopdf_options['windowSize']`: `[1440, 780]`, pour la taille de la fenêtre utilisée pae le navigateur
   - `htmltopdf_options['userAgent']`: `YesWiki/4.0`
   - `htmltopdf_options['startupTimeout']`: `30`, (en secondes) pour le temps d'attente pour le démarrage du navigateur (en général, il ne met que quelques dizaines de milli-secondes)
   - `htmltopdf_options['sendSyncDefaultTimeout']`: `10000`, (en milli-secondes) pour le temps d'attente pour la fin du rendu de la page (en général, celui-ci prend quelques secondes)
  3. si vous utilisez `chromium` derrière un [reverse proxy](https://fr.wikipedia.org/wiki/Proxy_inverse) (ce qui peut-être le cas lors de l'installation dans un conteneur _Docker_), il peut être nécessaire de configurer l'option `htmltopdf_base_url` manuellement via ftp pour indiquer l'adresse locale du wiki pour le reverse proxy.
    - exemple, si l'adresse du site dans le réseau local pour  le serveur HTTPS (Nginx/Apache) dans le conteneur _Docker_ est `http://localhost:8080/?`, alors il faut mettre cette adresse dans `htmltopdf_base_url` tout en conservant `'base_url' => 'https://example.com/?'`, l'adresse de votre site sur internet

?> Pour les développeureuses, la liste complète des paramètres disponibles est définie dans le fichier `tools/publication/vendor/chrome-php/chrome/src/Browser/BrowserProcess.php` méthodes `start()` et `getArgsFromOptions()`.

### Utilisation de `chromium` sur un autre site YesWiki

?> Quand il n'est pas possible d'utiliser `chromium` sur votre wiki (pas d'accès aux droits administrateurs, pas la présence de l'extension php `ext-sockets`, etc), vous pouvez utiliser l'extension `publication` d'un autre YesWiki.

Pour ceci:
 1. définir dans la page [GererConfig](?GererConfig 'Page config :ignore') dans la partie `publication`, paramètre `htmltopdf_service_url`, l'adresse d'un YesWiki disposant de l'extension publication (ex. : https://yeswiki.net/?AccueiL/pdf). L'important est de fournir le chemin vers le handler `/pdf`
 2. contacter l'administrateurice du site concerné pour lui demander d'ajouter votre nom de domaine aux domaines autorisés.
    - l'administrateurice concernée devra alors modifier le paramètre `htmltopdf_service_authorized_domains` dans la page [GererConfig](?GererConfig 'Page config :ignore') dans la partie `publication` (quelques exemples de valeurs possibles : `['example.com']` ou `['example.com','wiki.example.com','example.net']`)

## Utilisation détaillée

Cette partie décrit de façon détaillée les paramètres pour les actions et handlers utilisés. Pour les explications de prise en main, veuillez vous rendre [en haut de ce fichier](?id=prise-en-main).

### Liste des actions

Cette extension propose les actions suivantes :

|**Action**|**Description**|**Périmètre**|
|:-|:-|:-|
|`{{publicationgenerator}}`|Configurateur d'un `ebook` pour le sauvegarder dans le wiki|Dans une page|
|`{{publicationlist}}`|Liste des `ebook` configurés dans le wiki|Dans une page|
|`{{bazar2publication}}`|Bouton pour imprimer les fiches affichées dans un template `bazar` au lieu d'imprimer la page courante|Dans une page contenant l'action `{{bazarliste}}`|
|---|---|---|
|`{{pagebreak}}`|Pour indiquer un saut de page|Dans une page servant de `template` ou une page de définition d'`ebook`|
|`{{blankpage}}`|Pour indiquer une page vide|Dans une page servant de `template` ou une page de définition d'`ebook`|
|`{{listcontrib}}`|Pour indiquer la liste des contributeurs d'un `ebook`|Dans une page servant de `template` ou une page de définition d'`ebook`|
|`{{publication-template}}`|à combiner avec l'action `{{bazar2publication tempaltepage="..."}}` pour signaler l'emplacement réservé à l'injection du contenu|Dans une page servant de `template` ou une page de définition d'`ebook`|

_Les actions peuvent toutes êtres configurées en utilisant le bouton composant lors de l'édition de la page où elles sont présentes._

### Liste des handlers

Cette extension propose les handlers suivantes :

|**Handler**|**Description**|
|:-|:-|
|`/preview`|Pour afficher la page concernée avec un rendu dédié à l'impression|
|`/pdf`|Pour lancer l'impression du rendu obtenu avec le handler `/preview`|

#### handler `/preview`

Ce handler affiche la page dans un rendu prêt à être imprimé.

Ce handler peut utiliser les paramètres suivants.

|**paramètre**|**valeurs possibles**|**Détail**|**Contrainte**|
|:-|:-|:-|:-|
|`&layout=<layout-name>`|non défini ou `single-page` ou `recto-folio`|Choix du type d'impression dans le cas d'un affichage de type `fanzine`|Doit correspondre au nom d'un fichier `.svg` situé dans `tools/publication/styles/fanzine-layouts/` sans `.svg`|
|`&browserPrintAfterRendered=1`|non défini ou `1` ou `yes`|Déclenche automatiquement l'impression via le navigateur dès que la prévisualisation est prête si la valeur est `1`|Doit correpsondre à la valeur d'un `boolean`|
|`&via=bazarliste`|non défini ou `bazarliste`|Si la valeur est `bazarliste`, fait le rendu des fiches sélectionnées par l'action `{{bazarliste}}` au lieu de rendre la page elle-même||
|`&template-page=TaG`|non défini ou nom d'une page|Définit la page à utiliser comme modèle|Doit être une chaîne de caractère|
|`&query=bf_name=value1|bf_name2=value3`|une reqûete de type `bazarliste`|Permet de filtrer la liste des fiches à afficher dans le cas de `&via=bazarliste`|Doit respecter la syntaxe utilisé par `bazar`|

#### handler `/pdf`

Ce handler lance l'impression à partir de la prévisualisation.

Ce handler peut utiliser les paramètres suivants.

|**paramètre**|**valeurs possibles**|**Détail**|**Contrainte**|
|:-|:-|:-|:-|
|`&refresh=1`|non défini ou `1` ou `yes`|Force la régénération du pdf associé (normalement uniquement pour un ou une administraterurice)|Doit correpsondre à la valeur d'un `boolean`|
|`&url=<url-encoded>`|non défini ou chaîne de caractère|url de la prévisualisation de la page à imprimer (à utiliser dans le cas d'un appel depuis un site externe)||
|`&urlPageTag=TaG`|non défini ou chaîne de caractère|Nom de la page à imprimer (si pas défini, le tag `publication` est utilisé)||

<div style="text-align:center;">

[Modifier cette page sur GitHub](https://github.com/YesWiki/yeswiki-extension-publication/edit/doc/docs/fr/README.md)

</div>
