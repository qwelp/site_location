<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true) die();
$arComponentParameters = [
  'PARAMETERS' => [
    'SHOW_SECTION_TREE' => [ 'PARENT' => 'BASE','NAME' => 'Показывать дерево разделов','TYPE' => 'CHECKBOX','DEFAULT' => 'Y'],
    'USE_AJAX_SEARCH' => [ 'PARENT' => 'BASE','NAME' => 'AJAX-поиск','TYPE' => 'CHECKBOX','DEFAULT' => 'Y'],
    'CACHE_TIME' => ['DEFAULT' => 3600],
  ]
];
