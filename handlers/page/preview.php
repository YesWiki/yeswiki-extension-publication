<?php

echo <<<EOF
<!DOCTYPE html>
<html>
  <head>
    <title>Test</title>
    <link rel="stylesheet" href="tools/wkebook/presentation/styles/print.css">
    <link rel="stylesheet" href="tools/wkebook/presentation/styles/preview.css">
    <script defer src="tools/wkebook/libs/vendor/pagedjs/paged.polyfill.js"></script>
  </head>

  <body>
    {$this->format($this->page["body"])}
  </body>
</html>
EOF;
