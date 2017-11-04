<?php

namespace DreamFactory\Core\Script\Resources\System;

use DreamFactory\Core\Resources\System\ReadOnlySystemResource;
use DreamFactory\Core\Script\Contracts\ScriptEngineTypeInterface;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Utility\ResourcesWrapper;
use ScriptEngineManager;

class ScriptType extends ReadOnlySystemResource
{
    /**
     * {@inheritdoc}
     */
    protected static function getResourceIdentifier()
    {
        return 'name';
    }

    /**
     * Handles GET action
     *
     * @return array
     * @throws \DreamFactory\Core\Exceptions\NotFoundException
     */
    protected function handleGET()
    {
        if (!empty($this->resource)) {
            /** @type ScriptEngineTypeInterface $type */
            if (null === $type = ScriptEngineManager::getScriptEngineType($this->resource)) {
                throw new NotFoundException("Script engine type '{$this->resource}' not found.");
            }

            return $type->toArray();
        }

        $resources = [];
        $types = ScriptEngineManager::getScriptEngineTypes();
        /** @type ScriptEngineTypeInterface $type */
        foreach ($types as $type) {
            $resources[] = $type->toArray();
        }

        return ResourcesWrapper::wrapResources($resources);
    }

    protected function getApiDocSchemas()
    {
        $wrapper = ResourcesWrapper::getWrapper();

        return [
            'ScriptTypesResponse'              => [
                'type'       => 'object',
                'properties' => [
                    $wrapper => [
                        'type'        => 'array',
                        'description' => 'Array of registered script types.',
                        'items'       => [
                            '$ref' => '#/components/schemas/ScriptTypeResponse',
                        ],
                    ],
                ],
            ],
            'ScriptTypeResponse'    => [
                'type'       => 'object',
                'properties' => [
                    'name'              => [
                        'type'        => 'string',
                        'description' => 'Identifier for the service type.',
                    ],
                    'label'           => [
                        'type'        => 'string',
                        'description' => 'Displayable label for the service type.',
                    ],
                    'description'      => [
                        'type'        => 'string',
                        'description' => 'Description of the service type.',
                    ],
                    'sandboxed'    => [
                        'type'        => 'boolean',
                        'description' => 'Is this scripting option sandboxed from the rest of the system?',
                    ],
                    'supports_inline_execution'    => [
                        'type'        => 'boolean',
                        'description' => 'Does this script type support running scripts inline?',
                    ],
                ],
            ],
        ];
    }
}