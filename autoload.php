<?php

spl_autoload_register(function ($class) {
    $file = str_replace(array('/', '\\', '//'), DIRECTORY_SEPARATOR, __DIR__ . "/$class.php");
    if (file_exists($file)) {
        require_once($file);
        return;
    }
});
