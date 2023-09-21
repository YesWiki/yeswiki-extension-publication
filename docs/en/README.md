# Extension Publication

Publication extension has mainly been developped by Oncle Tom, alias, [Thomas Parisot](https://github.com/thom4parisot/), and it is currently maintained by [MrFlos](https://github.com/MrFlos). It corresponds to the need, often described by YesWiki'users, to easily generate publication, from the content of their wiki : 
 - books / booklets
 - fanzines
 - newsletters

Publication gives the possibility to manage 4 steps to make easier the tasks:
 - [Select elements of a publication](/tools/publication/docs/fr/README?id=select-elements-of-a-publication).
 - Organise elements into a publication.
 - Generate and save the publication.
 - [Create the PDF](/tools/publication/docs/fr/README?id=create-the-pdf)

!> For the forth step, `chromium` software shoud be [installed](?id=installation-of-chromium) on the server. No fear, if it is not the case, alternative solutions exist.


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

## More precise usage

See french help at this [address](/tools/publication/docs/fr/?id=utilisation-d%c3%a9taill%c3%a9e)

<div style="text-align:center;">

[Modify this page on GitHub](https://github.com/YesWiki/yeswiki-extension-publication/edit/doc/docs/en/README.md)

</div>
