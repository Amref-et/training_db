<?php

if (isset($_GET['__path']) && is_string($_GET['__path']) && $_GET['__path'] !== '') {
    $path = ltrim($_GET['__path'], '/');
    $query = $_GET;
    unset($query['__path']);

    $queryString = http_build_query($query);
    $requestUri = '/test/hil-v2/'.$path;

    if ($queryString !== '') {
        $requestUri .= '?'.$queryString;
    }

    $_SERVER['REQUEST_URI'] = $requestUri;
    $_SERVER['QUERY_STRING'] = $queryString;
    $_SERVER['SCRIPT_NAME'] = '/test/hil-v2/index.php';
    $_SERVER['PHP_SELF'] = '/test/hil-v2/index.php';
    $_SERVER['SCRIPT_FILENAME'] = realpath(__DIR__.'/public/index.php') ?: __DIR__.'/public/index.php';
    $_GET = $query;
    $_REQUEST = array_merge($query, $_POST);
}

require __DIR__.'/public/index.php';
