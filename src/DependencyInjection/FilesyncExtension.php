<?php
declare(strict_types=1);

/*
 * 	This File is part of Toteph42 FilesyncBundle
 *
 *	@copyright	(c) 2024 Florian Daeumling, Germany. All right reserved
 * 	@license 	https://github.com/toteph42/filesync/blob/master/LICENSE
 */

namespace Toteph42\FilesyncBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class FilesyncExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('commands.yaml');
        $loader->load('services.yaml');
    }
}
