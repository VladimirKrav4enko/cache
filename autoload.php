<?php
namespace Icorelab\Cache;

class Autoloader
{
    const debug = 0;
    const ROOT = __DIR__."/";
    protected static $autoloadDirs = [
        'classes',
    ];

    /**
     * Autoloader constructor.
     */
    public function __construct(){}

    /**
     * @param $class
     */
    public static function autoload($class){
        foreach (self::$autoloadDirs as $dir){
            self::loadClass($class, $dir);
        }
    }

    /**
     * @param $class
     * @param string $dir
     */
    public static function loadClass($class, $dir=""){
        if($dir){$dir.= "/";}

        $classArr = explode("\\",  $class);
        $className = $classArr[count($classArr)-1];

        $filepath = self::ROOT."/".$dir.$className.".php";

        if (file_exists($filepath))
        {
            require_once($filepath);
            spl_autoload(strtolower(str_replace("\\", "/", $class)));
        }

    }
}


\spl_autoload_register('Icorelab\Cache\Autoloader::autoload');
