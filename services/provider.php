<?php

/**
 * @package     SquidForm
 * @subpackage  plg_squidform_scannertools
 * @license     GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use SquidForm\Plugin\Squidform\ScannerTools\Extension\ScannerTools;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            $container->lazy(ScannerTools::class, function (Container $container) {
                $plugin = new ScannerTools((array) PluginHelper::getPlugin('squidform', 'scannertools'));
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            })
        );
    }
};
