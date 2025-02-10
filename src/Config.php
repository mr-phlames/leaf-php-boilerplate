<?php

declare(strict_types=1);

namespace Leaf;

/**
 * Leaf Config
 * -------------
 * Configure your leaf app
 */
class Config
{
    /**
     * Leaf application context
     * @var array
     */
    protected static $context = [
        'app' => null,
        'app.down' => false,
        'debug' => true,
        'eien.enabled' => true,
        'http.version' => '1.1',
        'log.writer' => null,
        'log.level' => null,
        'log.enabled' => false,
        'log.dir' => __DIR__ . '/../../../../storage/logs/',
        'log.file' => 'app.log',
        'log.open' => true,
        'mode' => 'development',
        'session' => true,
        'session.lifetime' => 60 * 60 * 24,
        'session.cookie' => ['secure' => false, 'httponly' => true, 'samesite' => 'lax'],
        'scripts' => [],
        'views.path' => null,
        'views.cachePath' => null,
    ];

    /**
     * Set configuration value(s)
     *
     * @param string|array $item The config(s) to set
     * @param mixed $value The value for config. Ignored if $item is an array.
     */
    public static function set($item, $value = null)
    {
        if (\is_string($item)) {
            static::$context[$item] = $value;
        } else {
            static::$context = \array_merge(static::$context, $item);
        }
    }

    /**
     * Attach view engine to Leaf view
     *
     * @param mixed $className The class to attach
     * @param string|null $name The key to save view engine with
     */
    public static function attachView($className, $name = null)
    {
        $class = new $className();
        $diIndex = $name ?? static::getDiIndex($class);

        if (!class_exists('Leaf\Core') && !static::getStatic('views.path') && is_dir('views')) {
            app()->vite();
            app()->config([
                'views.path' => 'views',
                'views.cache' => 'cache',
            ]);

            if ($className === 'Leaf\Blade') {
                $class->configure([
                    'views' => 'views',
                    'cache' => 'cache',
                ]);
            } elseif ($className === 'Leaf\BareUI') {
                $class->config('path', './views');
            }
        }

        static::set("views.$diIndex", $class);
    }

    /**
     * Return an attached view engine
     */
    public static function view($className)
    {
        return static::getStatic("views.$className");
    }

    /**
     * Grab context
     *
     * @param string|null $item The config to get. Returns all items if nothing is specified.
     */
    public static function get($item = null)
    {
        if (!$item) {
            return static::$context;
        }

        $value = static::$context[$item] ?? null;

        return static::isInvokable($value) ? $value(static::$context) : $value;
    }

    /**
     * Static getter
     * @param  string $item The item to get
     */
    public static function getStatic($item = null)
    {
        return static::$context[$item] ?? null;
    }

    /**
     * Get data value with key
     * @param  string $value   The item to check
     */
    public static function isInvokable($value)
    {
        return is_object($value) && method_exists($value, '__invoke');
    }

    /**
     * IteratorAggregate
     */
    public static function getIterator()
    {
        return new \ArrayIterator(static::$context);
    }

    /**
     * Ensure a value or object will remain globally unique
     *
     * @param  string   $key   The value or object name
     * @param  \Closure $value The closure that defines the object
     *
     * @return mixed
     */
    public static function singleton($key, $value)
    {
        static::set($key, function ($c) use ($value) {
            static $object;

            if (null === $object) {
                $object = $value($c);
            }

            return $object;
        });
    }

    /**
     * Protect closure from being directly invoked
     * @param  \Closure $callable A closure to keep from being invoked and evaluated
     * @return \Closure
     */
    public static function protect(\Closure $callable)
    {
        return function () use ($callable) {
            return $callable;
        };
    }

    /**
     * Add a script to attach to the leaf instance
     * @param callable $script The script to attach
     */
    public static function addScript(callable $script)
    {
        static::$context['scripts'][] = $script;
    }

    /**
     * Fetch set data keys
     * @return array This set's key-value data array keys
     */
    public static function keys()
    {
        return array_keys(static::$context);
    }

    /**
     * Does this set contain a key?
     * @param  string  $key The data key
     * @return bool
     */
    public static function has($key)
    {
        return array_key_exists($key, static::$context);
    }

    /**
     * Remove value with key from this set
     * @param  string $key The data key
     */
    public static function remove($key)
    {
        unset(static::$context[$key]);
    }

    /**
     * Countable
     */
    public static function count()
    {
        return count(static::$context);
    }

    /**
     * Clear all values
     */
    public static function reset()
    {
        static::$context = [
            'app' => static::$context['app'],
            'app.down' => static::$context['app.down'],
            'debug' => static::$context['debug'],
            'eien.enabled' => static::$context['eien.enabled'],
            'http.version' => static::$context['http.version'],
            'log.writer' => static::$context['log.writer'],
            'log.level' => static::$context['log.level'],
            'log.enabled' => static::$context['log.enabled'],
            'log.dir' => static::$context['log.dir'],
            'log.file' => static::$context['log.file'],
            'log.open' => static::$context['log.open'],
            'mode' => static::$context['mode'],
            'scripts' => [],
            'views.path' => static::$context['views.path'],
            'views.cachePath' => static::$context['views.cachePath'],
        ];
    }

    protected static function getDiIndex($class)
    {
        $fullName = \explode('\\', \strtolower(\get_class($class)));
        $className = $fullName[\count($fullName) - 1];

        return $className;
    }

    /**
     * Property Overloading
     */

    public static function __callstatic($key, $arguments)
    {
        return static::get($key);
    }
}
