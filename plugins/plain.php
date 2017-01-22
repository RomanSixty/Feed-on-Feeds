<?php

fof_add_item_filter('fof_plain');

function fof_plain($text)
{
    return strip_tags($text, "<a><b><i><blockquote>");
}