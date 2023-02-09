<?php
namespace DreamFactory\Core\Script\Components;

use DreamFactory\Core\Script\Contracts\ScriptEngineTypeInterface;
use DreamFactory\Core\Script\Contracts\ScriptingEngineInterface;
use Illuminate\Support\Str;

/**
 * Interface ScriptEngineType
 *
 * Something that defines a script type
 *
 * @package DreamFactory\Core\Contracts
 */
class ScriptEngineType implements ScriptEngineTypeInterface
{
    /**
     * @var string Designated type of a service
     */
    protected $name = '';
    /**
     * @var string Displayable label for this script type
     */
    protected $label = '';
    /**
     * @var string Description of this script type
     */
    protected $description = '';
    /**
     * @var boolean True if this script type can not access the rest of the OS
     */
    protected $sandboxed = false;
    /**
     * @var boolean True if this script type supports inline vs file execution
     */
    protected $supportsInlineExecution = false;
    /**
     * @var callable Designated callback for creating a service of this type
     */
    protected $factory = null;

    /**
     * Create a new script type instance.
     *
     * @param array $settings
     */
    public function __construct($settings = [])
    {
        foreach ($settings as $key => $value) {
            if (!property_exists($this, $key)) {
                // try camel cased
                $camel = Str::camel($key);
                if (property_exists($this, $camel)) {
                    $this->{$camel} = $value;
                    continue;
                }
            }
            // set real and virtual
            $this->{$key} = $value;
        }
    }

    /**
     * script type - matching registered script types
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Displayable script type label
     *
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * script type description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Is this script type only to be created once?
     *
     * @return boolean
     */
    public function isSandboxed()
    {
        return $this->sandboxed;
    }

    /**
     * Is this script type read only?
     *
     * @return boolean
     */
    public function supportsInlineExecution()
    {
        return $this->supportsInlineExecution;
    }

    /**
     * The factory callable for this script type
     *
     * @param array $config
     *
     * @return ScriptingEngineInterface|null
     */
    public function make(array $config = [])
    {
        return call_user_func($this->factory, $config);
    }

    /**
     * The configuration handler interface for this script type
     *
     * @return array | null
     */
    public function toArray()
    {
        return [
            'name'                      => $this->name,
            'label'                     => $this->label,
            'description'               => $this->description,
            'sandboxed'                 => $this->sandboxed,
            'supports_inline_execution' => $this->supportsInlineExecution,
        ];
    }
}
