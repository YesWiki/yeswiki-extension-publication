<?php

echo <<<EOF
<!DOCTYPE html>
<html>
  <head>
    <title>YesWiki Publication</title>
    <link rel="stylesheet" href="tools/publication/presentation/styles/print.css">
    <link rel="stylesheet" href="tools/publication/presentation/styles/preview.css">
    <script defer src="tools/publication/libs/vendor/pagedjs/paged.polyfill.js"></script>
  </head>

  <body>
    {$this->format($this->page["body"])}
  </body>
</html>
EOF;
