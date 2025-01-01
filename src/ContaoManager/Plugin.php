<?php
declare(strict_types=1);

/*
 * 	This File is part of Toteph42 FilesyncBundle
 *
 *	@copyright	(c) 2024 Florian Daeumling, Germany. All right reserved
 * 	@license 	https://github.com/toteph42/filesync/blob/master/LICENSE
 */

namespace Toteph42\FilesyncBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Toteph42\FilesyncBundle\FilesyncBundle;

class Plugin implements BundlePluginInterface
{

    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(FilesyncBundle::class)->setLoadAfter([ ContaoCoreBundle::class ]), ];
    }

}
