<?php

/**
 *  Universal echo function
 */

function _e($what = TRUE, $special = FALSE)
{
    echo '<pre>';

    if ($special) {
        echo  htmlspecialchars($what, ENT_QUOTES, 'UTF-8');
    } else if (is_bool($what)) {
        echo $what ? 'TRUE' : 'FALSE';
    } else if (is_null($what)) {
        echo 'NULL';
    } else {
        print_r($what);
    }

    if (!is_object($what) && !is_array($what)) {
        echo " [" . gettype($what) . "]";
    }

    echo '</pre>';
}
