<?php

$Module = array(
    'name'            => 'Stock level REST API',
    'variable_params' => true
);

$ViewList = array(
    'get_stock' => array(
        'functions'               => array( 'read' ),
        'script'                  => 'get_stock.php',
        'params'                  => array()
    )
);

