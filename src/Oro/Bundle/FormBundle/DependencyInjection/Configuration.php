<?php

namespace Oro\Bundle\FormBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Oro\Bundle\ConfigBundle\DependencyInjection\SettingsBuilder;

class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('oro_form');

        SettingsBuilder::append(
            $rootNode,
            [
                'wysiwyg_enabled' => ['value' => true, 'type' => 'bool'],
            ]
        );

        return $treeBuilder;
    }
}
