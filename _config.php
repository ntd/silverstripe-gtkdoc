<?php

/**
 * @package silverstripe-gtkdoc
 */

Object::add_extension('GtkdocSection', 'FulltextSearchable(\'"Title","MetaDescription","Content"\')');
Object::add_extension('ContentController', 'GtkdocControllerSearchExtension');
