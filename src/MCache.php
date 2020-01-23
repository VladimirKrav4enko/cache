<?php
namespace Icorelab\Cache;

/**
 *  // Пример кэширования блока
 *  if($cache = Cache::factory()->beginCache([__CLASS__, __FUNCTION__, ['key']], 360)){
 *      print "<p>some data</p>";
 *      $cache->endCache();
 *  }
 *
 *  // Пример частичного удаления кэша
 *  Cache::factory()->delete([__CLASS__], false);
 *
 *  // Пример кэширования с зависимостью
 *  $dependency = new DbDependency(['sql' => '
 *          SELECT UPDATE_TIME
 *          FROM   information_schema.tables
 *          WHERE  TABLE_SCHEMA = SCHEMA() AND TABLE_NAME = \'correspondence_outgoing\'
 *  ']);
 *
 *  $result = Cache::factory()->getOrSet([__CLASS__, __FUNCTION__, [User::GetAgentCode(), $filter, $fields, $sort, $rowsPerPage, $offset]], function() use ($filter, $fields, $sort, $rowsPerPage, $offset) {
 *      return $this->model->GetCorrespondenceHistory($filter, $fields, $sort, $rowsPerPage, $offset);
 *  }, 360, $dependency);
 *
 *
 * Class MCache
 * @property CacheInterface $cache
 */
class MCache{

    private $cache              = null; // Объект класса который будет использоваться для кэширования
    private $blockCacheParams   = null; // Параметры для кэширования блока beginCache/endCache
    private $defaultCacheType   = 'FileCache'; // Класс для работы с кэшем по умолчанию

    /**
     * Cache constructor.
     * @param string $cacheClass Имя класса который, будет использоваться для кэширования
     * @throws \Exception
     */
    public function __construct($cacheClass = '')
    {
        // Если не передан класс для кэширования, но в конфигурациях объявленна константа с классом по умолчанию
        if(!$cacheClass && $this->defaultCacheType){
            // Используем класс по умолчанию
            $cacheClass = $this->defaultCacheType;
        }

        try{
            // Создаем объект который будет использоваться для кэширования
            $cacheClass = "\Icorelab\Cache\\$cacheClass";
            $this->cache = new $cacheClass();
        }catch (\Exception $exception){
            // Вернем исключение
            throw new \Exception("Class $cacheClass not found");
        }

        // Если объект cache не реализует интерфейс CacheInterface
        if(!($this->cache instanceof CacheInterface)){
            // Вернем исключение
            throw new \Exception("Class $cacheClass not instanceof CacheInterface");
        }
    }

    /**
     * Фабрика экземпляров класса.
     *
     * @param string $cacheClass
     * @return MCache|CacheInterface
     * @throws \Exception
     */
    public static function factory( $cacheClass = '' ) {
        return new MCache( $cacheClass );
    }

    /**
     * Если произошло обращение к методам не опеределенным в данном классе
     * Но определенных в классе $cacheClass, то обращаемся к ним на прямую
     *
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if(method_exists($this->cache, $name)){
            return call_user_func_array(array($this->cache, $name), $arguments);
        }
    }

    /**
     * Устанавливает новое значение кэша по ключу, c учетом зависимости
     *
     * @param $key
     * @param $value
     * @param int $duration
     * @param null $dependency
     * @return mixed
     * @throws \Exception
     */
    public function set($key, $value, $duration = 0, $dependency = null){

        // Если определена зависимость
        if ($dependency !== null) {
            // Если зависимсоть является экземпляром класса Dependency
            if($dependency instanceof Dependency){
                // Сгенерируем начальное значение зависимости
                $dependency->evaluateDependency($this);
            }else{ // Если зависимость не является экземпларом класса Dependency
                // Вернем исключение
                throw new \Exception('Dependency object, not instance of Dependency abstract class');
            }
        }

        // Сериализуем значениие кэша, и класс зависмости
        $value = serialize([$value, $dependency]);

        // Сохраняем значение кэша
        return $this->cache->setValue($key, $value, $duration);
    }

    /**
     * Возвращает данные из кэша по ключу.
     * Если кэш не был найден возвращает значение $defaultValue,
     * Если значение $defaultValue не задано, вернет false
     *
     * @param array|string $key
     * @param $defaultValue
     * @return mixed
     */
    public function get($key, $defaultValue = null){
        // Получаем значение из кэша
        $cacheValue = $this->cache->getValue($key);

        // Если значение кэша не равно false
        if($cacheValue){
            // Десериализуем закэшированную запись
            $value = unserialize($cacheValue);
            // Проверяем, правильность десириализации, существует ли зависимость и была ли измененна зависимость
            if (is_array($value) && !($value[1] instanceof Dependency && $value[1]->isChanged($this))) {
                // Возвращаем значение кэша
                return $value[0];
            }
        }

        // Если указанно значение по умолчанию
        if (!is_null($defaultValue)){
            // Вернем значение по умолчанию
            return $defaultValue;
        }

        return false;
    }

    /**
     * Получить кэш по ключу, если кэш не найден занести новое значение
     *
     * @param string|array $key     Ключ
     * @param void $callable        Анонимная функция, которая возвращает новое значение для кэша
     * @param int $duration         Время жизни кэша в с
     * @param void $dependency      Зависимости
     *
     * @return mixed
     * @throws \Exception
     */
    public function getOrSet($key, $callable, $duration = 0, $dependency = null){
        // Если значение нашлось в кэше
        if (($cacheValue = $this->get($key)) !== false) {
            // Возвращаем значение
            return $cacheValue;
        }

        // Выполняем анонимную функцию из параметра $callable
        $value = call_user_func($callable, $this);

        // Если не удалось занести в кэш новое значение
        if (!$this->set($key, $value, $duration, $dependency)){
            // Вернем исключение
            throw new \Exception('Failed to set cache value for key ' . json_encode($key), __METHOD__);
        }

        return $value;
    }

    /**
     * Проверяет существует ли актуальный кэш, удовлетворяющий зависимости, по ключу $key
     *
     * @param $key
     * @return bool
     */
    public function exists($key)
    {
        $value = $this->get($key);

        return $value !== false;
    }

    /**
     * Сохранение нового значения кэша по ключу, если такого ключа ещё нет в кэше
     *
     * @param $key
     * @param $value
     * @param int $duration
     * @param null $dependency
     * @return bool|mixed
     * @throws \Exception
     */
    public function add($key, $value, $duration = 0, $dependency = null)
    {
        if(!$this->exists($key)){
            return $this->set($key, $value, $duration, $dependency);
        }

        return true;
    }

    /**
     * Кэширование участка кода. Начало
     *
     * @param $key
     * @param int $duration
     * @param null $dependency
     * @return bool
     */
    public function beginCache($key, $duration = 0, $dependency = null){
        // Если найден кэш, выведем значение кэша и вернем false
        if($this->exists($key)){ print $this->get($key); return false;}

        // Сохраняем принятые параметры
        $this->blockCacheParams = [
            'key'           => $key,
            'duration'      => $duration,
            'dependency'    => $dependency,
        ];

        // Открываем буфер
        ob_start();
        return $this;
    }

    /**
     * Кэширование участка кода. Конец
     *
     * @throws \Exception
     */
    public function endCache(){
        // Получаем содержимое буфера, и закрываем его
        $data = ob_get_clean();

        // Если параметры не определенны
        if(!$this->blockCacheParams){
            throw new \Exception('At first you must call the method beginCache');
        }

        // Получаем ранее сохраненные параметры
        $params = $this->blockCacheParams;

        // Сохраняем содержимое буфера в кэш
        $this->set($params['key'], $data, $params['duration'], $params['dependency']);

        // Выводим содержимое буфера
        print $data;
    }
}

