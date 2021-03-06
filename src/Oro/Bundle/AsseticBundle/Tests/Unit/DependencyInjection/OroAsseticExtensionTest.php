<?php

namespace Oro\Bundle\AsseticBundle\Tests\Unit\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;

use Oro\Bundle\AsseticBundle\DependencyInjection\OroAsseticExtension;
use Oro\Bundle\AsseticBundle\Tests\Unit\Fixtures;

use Oro\Component\Config\CumulativeResourceManager;

class OroAsseticExtensionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider loadDataProvider
     */
    public function testLoad(array $configs, array $expectedBundles, array $expectedConfiguration)
    {
        CumulativeResourceManager::getInstance()
            ->clear()
            ->setBundles($expectedBundles);

        $extension = new OroAsseticExtension();

        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', $expectedBundles);

        $extension->load($configs, $container);

        $this->assertEquals($expectedConfiguration, $container->getParameter('oro_assetic.raw_configuration'));

        $this->assertNotNull($container->getDefinition('oro_assetic.configuration'));
        $this->assertNotNull($container->getDefinition('oro_assetic.twig.extension'));
    }

    public function loadDataProvider()
    {
        $bundle = new Fixtures\TestBundle();

        return array(
            'minimal' => array(
                'configs' => array(
                    array()
                ),
                'expectedBundles' => array(),
                'expectedConfiguration' => array(
                    'css_debug_groups' => array(),
                    'css_debug_all' => false,
                    'css' => array()
                )
            ),
            'full' => array(
                'configs' => array(
                    array(
                        'css_debug' => array('css_group'),
                        'css_debug_all' => true,
                    )
                ),
                'expectedBundles' => array($bundle->getName() => get_class($bundle)),
                'expectedConfiguration' => array(
                    'css_debug_groups' => array('css_group'),
                    'css_debug_all' => true,
                    'css' => array(
                        'css_group' => array(
                            'first.css',
                            'second.css'
                        )
                    )
                )
            ),
        );
    }
}
