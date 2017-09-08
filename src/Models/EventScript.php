<?php

namespace DreamFactory\Core\Script\Models;

use DreamFactory\Core\Exceptions\ServiceUnavailableException;
use DreamFactory\Core\Models\BaseSystemModel;
use DreamFactory\Core\Script\Events\EventScriptDeletedEvent;
use DreamFactory\Core\Script\Events\EventScriptModifiedEvent;
use Illuminate\Database\Query\Builder;

/**
 * EventScript
 *
 * @property string  $name
 * @property string  $type
 * @property string  $content
 * @property array   $config
 * @property boolean $is_active
 * @property boolean $allow_event_modification
 * @method static Builder|EventScript whereIsActive($value)
 * @method static Builder|EventScript whereName($value)
 * @method static Builder|EventScript whereType($value)
 */
class EventScript extends BaseSystemModel
{
    /**
     * @const string The private cache file
     */
    const CACHE_PREFIX = 'script.';
    /**
     * @const integer How long a EventScript cache will live, 1440 = 24 minutes (default session timeout).
     */
    const CACHE_TTL = 1440;

    /**
     * The name of the "created at" column.
     *
     * @var string
     */
    const CREATED_AT = 'created_date';

    /**
     * The name of the "updated at" column.
     *
     * @var string
     */
    const UPDATED_AT = 'last_modified_date';

    protected $table = 'event_script';

    protected $primaryKey = 'name';

    protected $fillable = [
        'name',
        'type',
        'content',
        'config',
        'storage_service_id',
        'storage_path',
        'scm_reference',
        'scm_repository',
        'is_active',
        'allow_event_modification'
    ];

    protected $casts = [
        'is_active'                => 'boolean',
        'allow_event_modification' => 'boolean',
        'storage_service_id'       => 'integer',
    ];

    public $incrementing = false;

    public static function boot()
    {
        parent::boot();

        static::saved(
            function (EventScript $service){
                event(new EventScriptModifiedEvent($service));
            }
        );

        static::deleted(
            function (EventScript $service){
                event(new EventScriptDeletedEvent($service));
            }
        );
    }

    public function validate($data, $throwException = true)
    {
        if (!empty($disable = config('df.scripting.disable'))) {
            switch (strtolower($disable)) {
                case 'all':
                    throw new ServiceUnavailableException("All scripting is disabled for this instance.");
                    break;
                default:
                    $type = (isset($data['type'])) ? $data['type'] : null;
                    if (!empty($type) && (false !== stripos($disable, $type))) {
                        throw new ServiceUnavailableException("Scripting with $type is disabled for this instance.");
                    }
                    break;
            }
        }

        return parent::validate($data, $throwException);
    }
}