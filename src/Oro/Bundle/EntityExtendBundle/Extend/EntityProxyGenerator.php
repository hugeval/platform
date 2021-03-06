<?php

namespace Oro\Bundle\EntityExtendBundle\Extend;

use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;

class EntityProxyGenerator
{
    /** @var ConfigManager */
    protected $configManager;

    /** @var string */
    protected $kernelCacheDir;

    /** @var string */
    protected $cacheDir;

    /**
     * @param ConfigManager $configManager
     * @param string        $cacheDir
     */
    public function __construct(ConfigManager $configManager, $cacheDir)
    {
        $this->configManager  = $configManager;
        $this->kernelCacheDir = $cacheDir;
        $this->cacheDir       = $cacheDir;
    }

    /**
     * Gets the cache directory
     *
     * @return string
     */
    public function getCacheDir()
    {
        return $this->cacheDir;
    }

    /**
     * Sets the cache directory
     *
     * @param string $cacheDir
     */
    public function setCacheDir($cacheDir)
    {
        $this->cacheDir = $cacheDir;
    }

    /**
     * Generate doctrine proxy classes for extended entities
     */
    public function generateProxies()
    {
        $em = $this->configManager->getEntityManager();

        $isAutoGenerated = $em->getConfiguration()->getAutoGenerateProxyClasses();
        if (!$isAutoGenerated) {
            $proxyDir = $em->getConfiguration()->getProxyDir();
            if (!empty($this->cacheDir)
                && $this->kernelCacheDir !== $this->cacheDir
                && strpos($proxyDir, $this->kernelCacheDir) === 0
            ) {
                $proxyDir = $this->cacheDir . substr($proxyDir, strlen($this->kernelCacheDir));
            }
            $extendConfigProvider = $this->configManager->getProvider('extend');
            $extendConfigs        = $extendConfigProvider->getConfigs(null, true);
            $metadataFactory      = $em->getMetadataFactory();
            $proxyFactory         = $em->getProxyFactory();
            foreach ($extendConfigs as $extendConfig) {
                if (!$extendConfig->is('is_extend')) {
                    continue;
                }
                if ($extendConfig->in('state', [ExtendScope::STATE_NEW])) {
                    continue;
                }

                $entityClass   = $extendConfig->getId()->getClassName();
                $proxyFileName = $proxyDir . DIRECTORY_SEPARATOR . '__CG__'
                    . str_replace('\\', '', $entityClass) . '.php';
                $metadata      = $metadataFactory->getMetadataFor($entityClass);

                $proxyFactory->generateProxyClasses([$metadata], $proxyDir);
                clearstatcache(true, $proxyFileName);
            }
        }
    }
}
