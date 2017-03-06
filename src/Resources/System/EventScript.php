<?php

namespace DreamFactory\Core\Script\Resources\System;

use DreamFactory\Core\Contracts\HttpStatusCodeInterface;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\BatchException;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Resources\System\BaseSystemResource;
use DreamFactory\Core\Script\Models\EventScript as EventScriptModel;
use DreamFactory\Core\Utility\ResourcesWrapper;
use DreamFactory\Core\Utility\ResponseFactory;

/**
 * Class Event
 *
 * @package DreamFactory\Core\Resources
 */
class EventScript extends BaseSystemResource
{
    /**
     * @var string DreamFactory\Core\Models\BaseSystemModel Model Class name.
     */
    protected static $model = EventScriptModel::class;

    protected function handlePOST()
    {
        if (!empty($this->resource)) {
            if (empty($record = $this->getPayloadData())) {
                throw new BadRequestException('No record(s) detected in request.' . ResourcesWrapper::getWrapperMsg());
            }

            /** @var EventScriptModel $modelClass */
            $modelClass = static::$model;
            $params = $this->request->getParameters();
            if (empty($modelClass::find($this->resource))) {
                try {
                    $result = $modelClass::bulkCreate([$record], $params);
                    $result = current($result);
                } catch (BatchException $ex) {
                    $result = $ex->pickResponse(0);
                    if ($result instanceof \Exception) {
                        throw $result;
                    }
                } catch (\Exception $ex) {
                    throw new InternalServerErrorException($ex->getMessage());
                }

                $result = ResourcesWrapper::cleanResources($result, false, static::getResourceIdentifier(), ApiOptions::FIELDS_ALL);

                return ResponseFactory::create($result, null, HttpStatusCodeInterface::HTTP_CREATED);
            } else {
                $result = $modelClass::updateById($this->resource, $record, $params);
                $result = ResourcesWrapper::cleanResources($result, false, static::getResourceIdentifier(), ApiOptions::FIELDS_ALL);

                return ResponseFactory::create($result, null, HttpStatusCodeInterface::HTTP_OK);
            }
        }

        return parent::handlePOST();
    }
}