<?php

if ($this->config['htmltopdf_base_url']) {
  ['scheme' => $scheme, 'host' => $host, 'port' => $port] = parse_url($this->config['base_url']);
  $base_url = $scheme . '://' . $host . (!$port || (string)$port === '80' ? '' : ':'.$port) . '/';

  ['scheme' => $scheme, 'host' => $host, 'port' => $port] = parse_url($this->config['htmltopdf_base_url']);
  $new_base_url = $scheme . '://' . $host . (!$port || (string)$port === '80' ? '' : ':'.$port) . '/';

  $full_request_url = $_SERVER['REQUEST_SCHEME'] . '://'. $_SERVER['HTTP_HOST'] . ((string)$_SERVER['SERVER_PORT'] === '80' ? '' : ':'.$_SERVER['SERVER_PORT']) . $_SERVER['REQUEST_URI'];

  // Replaces https://example.com/?Accueil by http://localhost:8000/?Accueil
  // Replaces https://example.com/favicon.ico by http://localhost:8000/favicon.ico
  if (strpos($full_request_url, $new_base_url) === 0) {
    $plugin_output_new = str_replace([$this->config['base_url'], $base_url], [$this->config['htmltopdf_base_url'], $new_base_url], $plugin_output_new);
  }
}

