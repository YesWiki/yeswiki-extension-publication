# Extension Publication

Publication extension has mainly been developped by Oncle Tom, alias, [Thomas Parisot](https://github.com/thom4parisot/), and it is currently maintained by [MrFlos](https://github.com/MrFlos). It corresponds to the need, often described by YesWiki'users, to easily generate publication, from the content of their wiki : 
 - books / booklets
 - fanzines
 - newsletters

Publication gives the possibility to manage 4 steps to make easier the tasks:
 - [Select elements of a publication](?id=turning-the-pdf-design-interface-on).
 - Organise elements into a publication.
 - Generate and save the publication.
 - [Create the PDF](?id=printing-bazar-results-bazar2publication)

!> For the forth step, `chromium` software shoud be [installed](?id=installation-of-chromium) on the server. No fear, if it is not the case, alternative solutions exist.

----

## Getting started

### Generating publications: `{{publicationgenerator}}`

This is the action showing the tailored PDF generation interface.

![PDF generation interface](images/screenshot-edit.png 'PDF generation interface')

#### Turning the PDF design interface on

In the page where you want it shown:

 - go to `components`
 - Publication
 - Publication generator

Click "advanced options" to reach every setting.

!> TODO: add the missing file below

![Interface with advanced options open](images/missing-file.png 'Interface with advanced options open')

#### Settings

Either leave the following fields empty, and the readers do the work themselves, or fill
them in and decide whether readers may change them.

- `Publication mode`: leave it alone, a PDF is what we want here. The newsletter option is
  currently broken.
- `Page selection`: left empty, every page and bazar entry of the wiki shows up. Narrow it
  down:
  - `2` for **the bazar entries of form 2**
  - `2(bf_auteur=Rabelais)` for **the entries of form 2 whose author is Rabelais**. For
    more elaborate requests, see the [query syntax](https://yeswiki.net/?DocQuery).
  - `pages` for **the wiki pages of the site**
  - `pages(Rousseau)` for **the wiki pages tagged Rousseau**

    Two tricks:
    - selections combine, separated by commas: `2(bf_auteur=Rabelais), pages(Rousseau)`
    - as soon as you type in that field, a "Label of each selection group" field appears
      below. It adds a title above each batch of pages: put the wanted titles separated by
      commas, `Auteur Rabelais, Auteur Rousseau`
- `Start page`: the name of the page used as the front cover
- `End page`: the name of the page used as the back cover
- `Read only`: tick it and readers cannot change the proposals
- `Show all pages by default in the page list`: a fresh wiki carries many default pages,
  hidden from publication unless this is ticked
- `Cover image`: the web address of the image, on your wiki or anywhere else. Right click
  the image, then "copy link". With this, no start page is needed.
- `Cover title`: the title used to build the cover automatically. No start page needed.
- `Cover description`: the description used to build the cover automatically. No start page
  needed.
- `Author`: the author
- `Pages used as chapter separators`: with pages about Rabelais and others about Rousseau,
  naming two wiki pages here as chapter heads makes the publication read well:
  `PageChapitreRabelais, PageChapitreRousseau`. Create those pages in your wiki and it
  works.
- `Template`: if you work with a designer, they will build entry rendering templates, and
  this is the field they need.

One parameter has to be written by hand: `ebookpagenameprefix`. It makes your ebook names
start with a prefix of your own, instead of the default `ebook`:

```
{{publicationgenerator outputformat="ebook" ebookpagenameprefix="MesBouquinsAmoi"}}
```

### Printing bazar results: `{{bazar2publication}}`

This action builds PDFs out of the requests made through a bazar form's facets. Facets are
the elements on the right that sort the results.

![Using facets on a map](images/screenshot-bazar-export.png 'Using facets on a map')

Facets work on entries displayed as a list, an agenda, a table and so on.

`{{bazar2publication}}` goes in the same page as your form results and adds a button
offering to build the PDF. In the example above, it is the green "Print the results"
button.

#### Adding `{{bazar2publication}}` through `components`

In the relevant page:

 - go to `components`
 - Publication
 - Print bazar results

!> TODO: add the missing file below

![Interface with advanced options open](images/missing-file.png 'Interface with advanced options open')

The settings are straightforward:

- `Title`: the text shown on your button
- `Icon`: the icon beside it
- `Class`: any button class, to make it yellow, large, full width. See the button action in
  the components.
- `Template page`: name a page holding an already generated ebook, with a front and back
  cover, and your content is inserted into that model to build a proper PDF.

### Listing the generated ebooks: `{{publicationlist}}`

![Example list of generated ebooks](images/screenshot-page-index.png 'Example list of generated ebooks')

#### Adding `{{publicationlist}}` through `components`

In the page where you want it shown:

 - go to `components`
 - Publication
 - Publication list

This action has a single setting, **the page prefix**.

 - By default every ebook generated in your wiki is listed.
 - With a prefix set, only the ebooks carrying it are listed.

Try the action both logged in as an admin and as a plain visitor. As an admin you can
delete the ebooks that were created; as a plain visitor you cannot.

----

## Configuration of extension

To work, the extension needs :
 - to have installed [`chromium`](https://www.chromium.org/Home) software on the server
 - or to use another YesWiki which already disposes of[`chromium`](https://www.chromium.org/Home)

### Installation of `chromium`

> **Prerequisite**:
> - dispos of a [`ssh`](https://en.wikipedia.org/wiki/Secure_Shell) access to the server
> - dispose of administrative command line access to the server
> - dispose of a server with PHP extension `ext-sockets` activated

If the server uses an operating system of type Ubuntu/Debian , it is possible to use the command line :

```bash
sudo apt install -y --no-install-recommends chromium
```

Once the installation is finished, it is possible to check the installation path with this command line :
```bash
which chromium
```

It should be `/usr/bin/chromium`. If not, keep the given path to configure the extension.

#### Adjustements of `chromium`

Adjustements of `chromium` can be done.

 1. it the software path is not `/usr/bin/chromium`, the rigth one should be copied into page [GererConfig](?GererConfig 'Page config :ignore') in part `publication`, parameter `htmltopdf_path`
 2. other parameters can be updated using a `ftp` software to acces to the server to modify the content of `wakka.config.php` file or into page [GererConfig](?GererConfig 'Page config :ignore') in part `publication` (but only few parameters are avaialable). Parameter `htmltopdf_options` gives the possibility to adjust that. Some examples:
   - `htmltopdf_options['windowSize']`: `[1440, 780]`, for the size of the wndow used by the browser on the server
   - `htmltopdf_options['userAgent']`: `YesWiki/4.0`
   - `htmltopdf_options['startupTimeout']`: `30`, (in seconds) for the waited time to start the browser (usually some milli-seconds)
   - `htmltopdf_options['sendSyncDefaultTimeout']`: `10000`, (in milli-seconds) for the waited time for the page rendering (usually some seconds)
  3. if using `chromium` behind [reverse proxy](https://en.wikipedia.org/wiki/Reverse_proxy) (that it could be the case is using a _Docker_ container), it could be needed to configure the option `htmltopdf_base_url` via ftp to indicate the local address of the website for the reverse proxy.
    - example, if website's address in local network for HTTPD server (Nginx/Apache) in _Docker_ container is `http://localhost:8080/?`, then this address should be used for `htmltopdf_base_url` parameter while keeping `'base_url' => 'https://example.com/?'`, website's address on the internet

?> For developers, the full liste of available parameters is defined in the `tools/publication/vendor/chrome-php/chrome/src/Browser/BrowserProcess.php` file, in methods `start()` and `getArgsFromOptions()`.

### Usage of `chromium` on another YesWiki

?> When it is not possible to use `chromium` on the wiki (no administrator access rights, no php extension `ext-sockets`, etc), it is possible to use `publication` extension of another yeswiki.

For this:
 1. define into page [GererConfig](?GererConfig 'Page config :ignore') in part `publication`, parameter `htmltopdf_service_url`, the address of a YesWiki with extension publication (ex. : https://yeswiki.net/?AccueiL/pdf). The important is to furnish the path to the handler `/pdf`
 2. contact administrator of the concerned website to ask to add the current ueswiki's domain to the list of authorized domains.
    - the concerned adminsitrator will have to modify the parameter `htmltopdf_service_authorized_domains` into page [GererConfig](?GererConfig 'Page config :ignore'), part `publication` (examples of possibles values : `['example.com']` or `['example.com','wiki.example.com','example.net']`)

### What do if print page does not display in `iframe` ?

If print page does not display in `iframe`, it is possible that server constraints of security prevent its display.

To authorize it, go as admin in page page [GererConfig](?GererConfig 'Page config :ignore') in part `Main parameters` and add `'pdf','pdfiframe'` to possible values in parameter `allowed_methods_in_iframe`.

## Detailed usage

This part describes the parameters of the actions and handlers in detail. For
getting started, see [the top of this file](?id=getting-started).

### List of actions

This extension provides the following actions:

|**Action**|**Description**|**Scope**|
|:-|:-|:-|
|`{{publicationgenerator}}`|Configures an `ebook` and saves it in the wiki|In a page|
|`{{publicationlist}}`|Lists the `ebook`s configured in the wiki|In a page|
|`{{bazar2publication}}`|Button printing the entries shown by a `bazar` template instead of the current page|In a page holding the `{{bazarliste}}` action|
|---|---|---|
|`{{pagebreak}}`|Marks a page break|In a page used as a `template`, or an `ebook` definition page|
|`{{blankpage}}`|Marks a blank page|In a page used as a `template`, or an `ebook` definition page|
|`{{listcontrib}}`|Lists the contributors of an `ebook`|In a page used as a `template`, or an `ebook` definition page|
|`{{publication-template}}`|Combined with `{{bazar2publication templatepage="..."}}`, marks where the content is injected|In a page used as a `template`, or an `ebook` definition page|

_Every action can be configured through the components button while editing the page it
sits in._

### List of handlers

This extension provides the following handlers:

|**Handler**|**Description**|
|:-|:-|
|`/preview`|Shows the page with a print-oriented rendering|
|`/pdf`|Prints the rendering obtained through the `/preview` handler|

#### The `/preview` handler

This handler shows the page ready to be printed. It takes the following parameters.

|**parameter**|**possible values**|**detail**|**constraint**|
|:-|:-|:-|:-|
|`&layout=<layout-name>`|unset, `single-page` or `recto-folio`|Print type for a `fanzine` rendering|Must match the name of an `.svg` file in `tools/publication/styles/fanzine-layouts/`, without `.svg`|
|`&browserPrintAfterRendered=1`|unset, `1` or `yes`|Set to `1`, printing through the browser starts as soon as the preview is ready|Must be a `boolean` value|
|`&via=bazarliste`|unset or `bazarliste`|Set to `bazarliste`, renders the entries selected by the `{{bazarliste}}` action instead of the page itself||
|`&template-page=TaG`|unset or a page name|The page to use as a template|Must be a string|
|`&query=bf_name=value1\|bf_name2=value3`|a `bazarliste` request|Filters the entries listed when `&via=bazarliste` is used|Must follow the syntax `bazar` uses|

#### The `/pdf` handler

This handler starts printing from the preview. It takes the following parameters.

|**parameter**|**possible values**|**detail**|**constraint**|
|:-|:-|:-|:-|
|`&refresh=1`|unset, `1` or `yes`|Forces the matching PDF to be rebuilt, normally for an administrator only|Must be a `boolean` value|
|`&url=<url-encoded>`|unset or a string|Url of the preview of the page to print, for a call coming from an external site||
|`&urlPageTag=TaG`|unset or a string|Name of the page to print. Unset, the `publication` tag is used.||


<div style="text-align:center;">

[Modify this page on GitHub](https://github.com/YesWiki/yeswiki-extension-publication/edit/master/docs/en/README.md)

</div>
