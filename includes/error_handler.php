<?php

function logError($message) {
    $file = __DIR__ . '/../logs/error_log.txt';
    $time = date("Y-m-d H:i:s");
    file_put_contents($file, "[$time] $message\n", FILE_APPEND);
}

function displayError($msg = "Something went wrong.") {
    echo "<div style='color:red;'>$msg</div>";
}

?>