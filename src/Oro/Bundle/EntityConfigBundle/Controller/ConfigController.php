<?php

namespace Oro\Bundle\EntityConfigBundle\Controller;

use Doctrine\ORM\QueryBuilder;

use Oro\Bundle\EntityConfigBundle\Tools\ConfigHelper;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Oro\Bundle\SecurityBundle\Annotation\Acl;

use Oro\Bundle\EntityConfigBundle\Entity\EntityConfigModel;
use Oro\Bundle\EntityConfigBundle\Entity\FieldConfigModel;
use Oro\Bundle\EntityConfigBundle\Metadata\EntityMetadata;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\EntityConfigBundle\Provider\PropertyConfigContainer;

use Oro\Bundle\EntityExtendBundle\Extend\ExtendManager;
use Oro\Bundle\TranslationBundle\Translation\Translator;

/**
 * EntityConfig controller.
 * @Route("/entity/config")
 * TODO: Discuss ACL impl., currently management of configurable entities can be on or off only
 * @Acl(
 *      id="oro_entityconfig_manage",
 *      label="oro.entity_config.action.manage",
 *      type="action",
 *      group_name=""
 * )
 */
class ConfigController extends Controller
{

    /**
     * Lists all configurable entities.
     * @Route("/", name="oro_entityconfig_index")
     * Acl(
     *      id="oro_entityconfig",
     *      label="oro.entity_config.action.view_entities",
     *      type="action",
     *      group_name=""
     * )
     * @Template()
     */
    public function indexAction(Request $request)
    {
        $actions       = [];
        $modules       = [];
        $configManager = $this->get('oro_entity_config.config_manager');

        foreach ($configManager->getProviders() as $provider) {
            foreach ($provider->getPropertyConfig()->getLayoutActions() as $config) {
                $actions[] = $config;
            }

            $modules = array_merge(
                $modules,
                $provider->getPropertyConfig()->getRequireJsModules()
            );
        }

        return [
            'buttonConfig' => $actions,
            'require_js'   => $modules,
        ];
    }

    /**
     * @Route("/update/{id}", name="oro_entityconfig_update")
     * Acl(
     *      id="oro_entityconfig_update",
     *      label="oro.entity_config.action.update_entity",
     *      type="action",
     *      group_name=""
     * )
     * @Template()
     */
    public function updateAction($id)
    {
        $entity  = $this->getDoctrine()->getRepository(EntityConfigModel::ENTITY_NAME)->find($id);
        $request = $this->getRequest();

        $form = $this->createForm(
            'oro_entity_config_type',
            null,
            ['config_model' => $entity]
        );

        if ($request->getMethod() == 'POST') {
            $form->submit($request);

            if ($form->isValid()) {
                //persist data inside the form
                $this->get('session')->getFlashBag()->add(
                    'success',
                    $this->get('translator')->trans('oro.entity_config.controller.config_entity.message.saved')
                );

                return $this->get('oro_ui.router')->redirectAfterSave(
                    ['route' => 'oro_entityconfig_update', 'parameters' => ['id' => $id]],
                    ['route' => 'oro_entityconfig_view', 'parameters' => ['id' => $id]]
                );
            }
        }

        /** @var ConfigProvider $entityConfigProvider */
        $entityConfigProvider = $this->get('oro_entity_config.provider.entity');

        return [
            'entity'        => $entity,
            'entity_config' => $entityConfigProvider->getConfig($entity->getClassName()),
            'form'          => $form->createView(),
        ];
    }

    /**
     * View Entity
     * @Route("/view/{id}", name="oro_entityconfig_view")
     * Acl(
     *      id="oro_entityconfig_view",
     *      label="oro.entity_config.action.view_entity",
     *      type="action",
     *      group_name=""
     * )
     * @Template()
     */
    public function viewAction(EntityConfigModel $entity)
    {
        list($moduleName, $entityName) = ConfigHelper::getModuleAndEntityNames($entity->getClassName());

        /** @var \Oro\Bundle\EntityConfigBundle\Config\ConfigManager $configManager */
        $configManager = $this->get('oro_entity_config.config_manager');

        /** @var ConfigProvider $entityConfigProvider */
        $entityConfigProvider = $this->get('oro_entity_config.provider.entity');

        /** @var ConfigProvider $extendConfigProvider */
        $extendConfigProvider = $this->get('oro_entity_config.provider.extend');
        $extendConfig         = $extendConfigProvider->getConfig($entity->getClassName());

        /**
         * TODO
         * refactor and place into Helper class
         */
        // generate link for Entity grid
        $link = '';
        /** @var EntityMetadata $metadata */
        if (class_exists($entity->getClassName())) {
            $metadata = $configManager->getEntityMetadata($entity->getClassName());
            if ($metadata && $metadata->routeName) {
                $link = $this->generateUrl($metadata->routeName);
            }

            if ($extendConfig->is('owner', ExtendManager::OWNER_CUSTOM)) {
                $link = $this->generateUrl(
                    'oro_entity_index',
                    ['id' => str_replace('\\', '_', $entity->getClassName())]
                );
            }

            /** @var QueryBuilder $qb */
            $qb = $this->getDoctrine()->getManager()->createQueryBuilder();
            $qb->select('count(entity)');
            $qb->from($entity->getClassName(), 'entity');
            $entityCount = $qb->getQuery()->getSingleScalarResult();
        } else {
            $entityCount = 0;
        }

        list ($layoutActions, $requireJsModules) = $this->getLayoutParams($entity);

        return [
            'entity'           => $entity,
            'entity_config'    => $entityConfigProvider->getConfig($entity->getClassName()),
            'entity_count'     => $entityCount,
            'link'             => $link,
            'entity_name'      => $entityName,
            'button_config'    => $layoutActions,
            'require_js'       => $requireJsModules,
        ];
    }

    /**
     * TODO: Check if this method ever used
     * Lists Entity fields
     * @Route("/fields/{id}", name="oro_entityconfig_fields", requirements={"id"="\d+"}, defaults={"id"=0})
     * @Template()
     */
    public function fieldsAction($id, Request $request)
    {
        $entity = $this->getDoctrine()->getRepository(EntityConfigModel::ENTITY_NAME)->find($id);

        list ($layoutActions, $requireJsModules) = $this->getLayoutParams($entity);

        return [
            'buttonConfig' => $layoutActions,
            'entity_id'    => $id,
            'entity_name'  => $entity->getClassName(),
            'require_js'   => $requireJsModules,
        ];
    }

    /**
     * @Route("/field/update/{id}", name="oro_entityconfig_field_update")
     * Acl(
     *      id="oro_entityconfig_field_update",
     *      label="oro.entity_config.action.update_entity_field",
     *      type="action",
     *      group_name=""
     * )
     * @Template()
     */
    public function fieldUpdateAction($id)
    {
        /** @var FieldConfigModel $field */
        $field   = $this->getDoctrine()->getRepository(FieldConfigModel::ENTITY_NAME)->find($id);
        $request = $this->getRequest();

        $form = $this->createForm(
            'oro_entity_config_type',
            null,
            ['config_model' => $field]
        );

        if ($request->getMethod() == 'POST') {
            $form->submit($request);

            if ($form->isValid()) {
                //persist data inside the form
                $this->get('session')->getFlashBag()->add(
                    'success',
                    $this->get('translator')->trans('oro.entity_config.controller.config_field.message.saved')
                );

                return $this->get('oro_ui.router')->redirectAfterSave(
                    ['route' => 'oro_entityconfig_field_update', 'parameters' => ['id' => $id]],
                    ['route' => 'oro_entityconfig_field_update', 'parameters' => ['id' => $id]],
                    ['route' => 'oro_entityconfig_view', 'parameters' => ['id' => $field->getEntity()->getId()]]
                );
            }
        }

        /** @var ConfigProvider $entityConfigProvider */
        $entityConfigProvider = $this->get('oro_entity_config.provider.entity');

        /** @var ConfigProvider $entityExtendProvider */
        $entityExtendProvider = $this->get('oro_entity_config.provider.extend');

        $entityConfig = $entityConfigProvider->getConfig($field->getEntity()->getClassName());
        $fieldConfig  = $entityConfigProvider->getConfig(
            $field->getEntity()->getClassName(),
            $field->getFieldName()
        );

        return [
            'entity_config' => $entityConfig,
            'field_config'  => $fieldConfig,
            'field'         => $field,
            'form'          => $form->createView(),
            'formAction'    => $this->generateUrl('oro_entityconfig_field_update', ['id' => $field->getId()]),
            'require_js'    => $entityExtendProvider->getPropertyConfig()->getRequireJsModules()
        ];
    }

    /**
     * @Route("/field/search/{id}", name="oro_entityconfig_field_search", defaults={"id"=0})
     * Acl(
     *      id="oro_entityconfig_field_search",
     *      label="oro.entity_config.action.field_search",
     *      type="action",
     *      group_name=""
     * )
     */
    public function fieldSearchAction($id)
    {
        $fields = [];
        if ($id) {
            $id = str_replace('_', '\\', $id);

            /** @var EntityConfigModel $entity */
            $entity = $this->getDoctrine()->getRepository(EntityConfigModel::ENTITY_NAME)
                ->findOneBy(['className' => $id]);

            if ($entity) {
                /** @var ConfigProvider $entityConfigProvider */
                $entityConfigProvider = $this->get('oro_entity_config.provider.entity');

                /** @var FieldConfigModel $fields */
                $entityFields = $this->getDoctrine()->getRepository(FieldConfigModel::ENTITY_NAME)
                    ->findBy(
                        [
                            'entity' => $entity->getId(),
                            'type'   => 'string'
                        ]
                    );

                /** @var Translator $translator */
                $translator = $this->get('translator');

                foreach ($entityFields as $field) {
                    $label = $entityConfigProvider->getConfig($id, $field->getFieldName())->get('label');
                    if (!$label) {
                        $label = $field->getFieldName();
                    }

                    $fields[$field->getFieldName()] = $translator->trans($label);
                }
            }
        }

        return new Response(json_encode($fields));
    }

    /**
     * Return configured layout actions and requirejs modules
     *
     * @param  EntityConfigModel $entity
     * @return array
     */
    protected function getLayoutParams(EntityConfigModel $entity)
    {
        $configManager    = $this->get('oro_entity_config.config_manager');
        $actions          = [];
        $requireJsModules = [];

        foreach ($configManager->getProviders() as $provider) {
            $layoutActions = $provider->getPropertyConfig()->getLayoutActions(PropertyConfigContainer::TYPE_FIELD);
            foreach ($layoutActions as $config) {
                if (isset($config['filter'])) {
                    foreach ($config['filter'] as $key => $value) {
                        if (is_array($value)) {
                            $error = true;
                            foreach ($value as $v) {
                                if ($provider->getConfig($entity->getClassName())->get($key) == $v) {
                                    $error = false;
                                }
                            }
                            if ($error) {
                                continue 2;
                            }
                        } elseif ($provider->getConfig($entity->getClassName())->get($key) != $value) {
                            continue 2;
                        }
                    }
                }

                if (isset($config['entity_id']) && $config['entity_id'] == true) {
                    $config['args'] = ['id' => $entity->getId()];
                }

                $actions[] = $config;
            }

            $requireJsModules = array_merge(
                $requireJsModules,
                $provider->getPropertyConfig(PropertyConfigContainer::TYPE_FIELD)->getRequireJsModules()
            );
        }

        return [$actions, $requireJsModules];
    }

    /**
     * @Route("/widget/info/{id}", name="oro_entityconfig_widget_info")
     * @Template
     */
    public function infoAction(EntityConfigModel $entity)
    {
        list($moduleName, $entityName) = ConfigHelper::getModuleAndEntityNames($entity->getClassName());

        /** @var ConfigProvider $entityConfigProvider */
        $entityConfigProvider = $this->get('oro_entity_config.provider.entity');

        /** @var ConfigProvider $extendConfigProvider */
        $extendConfigProvider = $this->get('oro_entity_config.provider.extend');
        $extendConfig         = $extendConfigProvider->getConfig($entity->getClassName());

        /** @var ConfigProvider $ownershipConfigProvider */
        $ownershipConfigProvider = $this->get('oro_entity_config.provider.ownership');
        $ownerTypes = $this->get('oro_organization.method.get_ownership_type')->execute();
        $ownerType = $ownershipConfigProvider->getConfig($entity->getClassName())->get('owner_type');
        $ownerType = $ownerTypes[empty($ownerType) ? 'NONE' : $ownerType];

        return [
            'entity'            => $entity,
            'entity_config'     => $entityConfigProvider->getConfig($entity->getClassName()),
            'entity_extend'     => $extendConfig,
            'entity_owner_type' => $ownerType,
            'entity_name'       => $entityName,
            'module_name'       => $moduleName,
        ];
    }

    /**
     * @Route("/widget/unique_keys/{id}", name="oro_entityconfig_widget_unique_keys")
     * @Template
     */
    public function uniqueKeysAction(EntityConfigModel $entity)
    {
        /** @var ConfigProvider $extendConfigProvider */
        $extendConfigProvider = $this->get('oro_entity_config.provider.extend');
        $extendConfig         = $extendConfigProvider->getConfig($entity->getClassName());

        return [
            'entity'     => $entity,
            'unique_key' => $extendConfig->get('unique_key')
        ];
    }

    /**
     * @Route("/widget/entity_fields/{id}", name="oro_entityconfig_widget_entity_fields")
     * @Template
     */
    public function entityFieldsAction(EntityConfigModel $entity)
    {
        return ['entity' => $entity];
    }
}
