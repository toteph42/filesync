<?php
declare(strict_types=1);

/*
 * 	This File is part of Toteph42 FilesyncBundle
 *
 *	@copyright	(c) 2024 Florian Daeumling, Germany. All right reserved
 * 	@license 	https://github.com/toteph42/file-notification/blob/master/LICENSE
 */

use Contao\CoreBundle\DataContainer\PaletteManipulator;

// Extend the default palette
PaletteManipulator::create()
    ->addField('filesync', 'personal_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_member')
;

// Add field
$GLOBALS['TL_DCA']['tl_member']['fields']['filesync'] = [
	'label' 		=> &$GLOBALS['TL_LANG']['tl_member']['filesync'],
	'inputType'     => 'checkbox',
	'eval'          => [ 'tl_class' => 'w50','feEditable' => true, 'feViewable' => true, ],
	'sql'           => [ 'type' => 'boolean', 'default' => true ]
];

