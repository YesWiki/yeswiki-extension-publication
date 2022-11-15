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
A noter que pour cette quatrième étape, chromium devra être installé sur votre serveur. Ceci dit, pas de panique si ce n'est pas le cas, des solutions alternatives existent.

## Générer des publications `{{publicationgenerator}}`

Et non, il ne s'agit pas d'un nom de dinosaure mais de l'action qui vous permettra d'afficher l'interface de génération de pdf sur mesure

![Interface de génération de PDF](images/screenshot-edit.png 'Interface de génération de PDF')

### Activer l'interface de conception de pdf

Pour activer cette interface, dans la page au sein de laquelle vous souhaiterez l'afficher :
 - Allez dans `composants`
 - Publication
 - Générateur de Publication

Vous obtiendrez l'interface suivante (en cliquant sur options avancées afin d'avoir accès à toutes les possibilités)

!> TODO : ajouter le fichier manquant ci-dessous

![Interface quand on clique sur options avancées](images/missing-file.png 'Interface quand on clique sur options avancées')

### Eléments paramétrables

- `NB :` soit vous ne mettez rien dans les champs suivants et dans ce cas les usagers devront faire le travail, soit vous remplissez les éléments en permettant (ou pas) au utilisateurices de modifier ces paramètres.
- `Mode de publication` : on ne touche à rien c'est bien un pdf que nous cherchons à générer, l'option newsletter est actuellement dans les choux
- `Sélection des pages` : Si vous ne spécifiez rien, toutes les pages et fiches bazar de votre wiki vont s'afficher... Nous allons donc ici spécifier ce que nous souhaitons afficher :
  - `2` si nous souhaitons afficher **les fiches bazar du formulaire n°2**
  - `2(bf_auteur=Rabelais)` pour **les fiches du formulaire 2 dont l'auteur est Rabelais** pour aller plus loin dans les requètes, vous pouvez vous servir de la [syntaxe de query](https://yeswiki.net/?DocQuery 'syntaxe de query')
  - `pages` pour **les pages wiki du site**
  - `pages(Rousseau)` pour afficher **les pages wiki qui ont comme tag Rousseau**
    
    A noter deux astuces :
    - vous pouvez combiner les demandes d'affichages de pages, il suffira de les séparer par une virgule : `2(bf_auteur=Rabelais), pages(Rousseau)` par exemple
    - dès que vous commencez à écrire dans ce champ, un autre champ apparaît en dessous "Libellé de chaque groupe de sélection" Cela permettra de rajouter un titre au dessus de chaque paquet de pages afin de s'y retrouver, il suffira de mettre les titres voulus séparés par une virgule : `Auteur Rabelais, Auteur Rousseau`
    
- `Page de démarrage` : Indiquer le nom de la page qui servira de première de couverture
- `Page de fin` : Indiquez le nom de la page qui servira de quatrième de couverture
- `Lecture seul` : Les usagers ne pourront pas modifier les propositions si vous cochez cette case
- `Montrer toutes les pages par défaut dans la liste des pages` : Quand wiki est installé, plein de pages par défaut sont créées. Par défaut, elles ne sont pas visibles dans publication, si vous cochez cette case, elles le seront
- `Image de la page de couverture` : indiquez ici l'adresse web de l'image qui pourra se trouver sur votre wiki ou ailleurs sur le web (clic droit sur l'image puis "copier le lien") (dans ce cas pas besoin de Page de démarrage)
- `Titre de la page de couverture` : Le titre qui permettra de générer automatiquement la page de couverture (dans ce cas pas besoin de Page de démarrage)
- `Description sur la page de couverture` : La description qui permettra de générer automatiquement la page de couverture (dans ce cas pas besoin de Page de démarrage)
- `Auteur` : spécifier l'auteur 
- `Pages servant de délimitation de chapitre` : Dans notre exemple nous aurons des pages associées à Rabelais et d'autres à Rousseau, si nous appelons ici deux pages wiki qui serviront de tête de chapitres, notre publication sera du plus bel effet : ""PageChapitreRabelais, PageChapitreRousseau"". il suffira de créer ces pages dans votre wiki et le tour sera joué
- `Template` : Si vous travaillez avec un graphiste, il réalisera certainement des templates de restitution des fiches bazar, il aura alors besoin de ce champ

NB. Il existe un paramètre qu'il vous faudra coder à la main si vous souhaitez l'utiliser c'est `ebookpagenameprefix`. Ce paramètre vous permettra de faire commencer le nom de vos ebooks par un préfixe qui sera singulier (sinon par défaut ce sera ebook)
Pour cela, il faudra rajouter dans le code de votre action : `ebookpagenameprefix="MesBouquinsAmoi"`
cela donnera par exemple la syntaxe
`{{publicationgenerator outputformat="ebook" ebookpagenameprefix="MesBouquinsAmoi"}}`

## Imprimer des résultats bazar : `{{bazar2publication}}`

Cette action vous permettra de générer des pdf issus des requêtes réalisées au travers des facettes du formulaire bazar.
Voilà à quoi ressemble l'utilisation de facette (les facettes sont les éléments sur la droite qui permettent de trier):

![Utilisation des facettes sur une carte](images/screenshot-bazar-export.png 'Utilisation des facettes sur une carte')

NB : Les facettes peuvent être utilisées sur un affichage des fiches sous forme de liste, agenda, tableau...

L'ajout de `{{bazar2publication}}` se fait dans la même page que l'affichage des résultats de votre formulaire bazar et génère un bouton proposant la possibilité de générer le fameux pdf. Sur l'exemple ci-dessus c'est le bouton vert "Imprimer les résultats".

### Ajouter l'action `{{bazar2publication}}` via `composants`

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

## Lister les ebooks générés : `{{publicationlist}}`

![Exemple de liste des ebooks générés](images/screenshot-page-index.png 'Exemple de liste des ebooks générés')

### Ajouter l'action `{{publicationlist}}` via `composants`

Pour activer cette interface, dans la page au sein de laquelle vous souhaiterez l'afficher :
 - Allez dans `composants`
 - Publication
 - Liste des Publications

Cette action n'a qu'un seul paramètre modifiable, **le prefix de page**.
 - Par défaut tous les ebooks générés au sein de votre wiki seront affichés.
 - Si vous ajoutez un prefix de page, seuls les ebooks ayant ce prefix seront affichés.

Enfin, testez cette action connectée comme admin, ou comme simple visiteurice.
Dans le premier cas, vous pourrez :
 - supprimer les ebooks créés
Mais vous ne pourrez pas le faire en étant simple visiteurice.

<div style="text-align:center;">

[Modifier cette page sur GitHub](https://github.com/YesWiki/yeswiki-extension-publication/edit/doc/docs/fr/README.md)

</div>