<?php
namespace Icorelab\Cache;

/**
 * Class FileCache
 */
class FileCache implements CacheInterface{

    protected $defaultDuration    = 86400;     // Время жизни кэша по умолчанию
    protected $cachePath          = '/cache';  // Путь к папке в которой будет храниться кэш
    protected $ccProbability      = 100;       // В 100 случаях из 1000000 очищать кэш от всех записей, во избежание засорения кэша
    protected $cacheFileSuffix    = '.cache';  // Расширение файла кэша
    protected $fileMode           = 0755;      // Права на файл с кэшем
    protected $dirsMode           = 0755;      // Права на папку с кэшем

    /**
     * FileCache constructor.
     */
    public function __construct()
    {
        // Если в конфигурациях объявленна константа с путем к папке с кэшом
        if(defined('CACHE_PATH')){
            // В качестве пути к кэшу, будем использовать путь опеределенный в константе
            $this->cachePath = CACHE_PATH;
        }

        // Если в конфигурациях объявленна константа с путем к корню проекта
        if(defined('PATH_PROJECT')){
            // Добавляем к пути папки с кэшем путь к корню проекта
            $this->cachePath = PATH_PROJECT . $this->cachePath;
        }

        // Если путь к дирректории с кэшем начинается не со слеша, дополним строку спереди слешем
        if(isset($this->cachePath[0]) && $this->cachePath[0] != DIRECTORY_SEPARATOR){
            $this->cachePath = DIRECTORY_SEPARATOR . $this->cachePath;
        }

    }

    /**
     * Возвращает данные из кэша по ключу.
     * Если кэш не был найден, то вернет false,
     *
     * @param array|string $key
     * @return bool|mixed|string
     * @throws Exception
     */
    public function getValue($key)
    {
        // Получаем путь к файлу с кэшем
        $cacheFile = $this->getCacheFile($key);

        // Если время доступа файла еще не истекло
        if (file_exists($cacheFile) && @filemtime($cacheFile) > time()) {
            // Читаем файл
            $fp = @fopen($cacheFile, 'r');
            if ($fp !== false) {
                @flock($fp, LOCK_SH);
                $cacheValue = @stream_get_contents($fp);
                @flock($fp, LOCK_UN);
                @fclose($fp);

                return $cacheValue;
            }
        }

        return false;
    }

    /**
     * Устанавливает новое значение кэша по ключу
     *
     * @param array|string $key
     * @param $value
     * @param int $duration
     * @return mixed|void
     * @throws Exception
     */
    public function setValue($key, $value, $duration = 0)
    {
        // Периодически чистим кэш, от просроченных записей
        $this->cc(false, false);

        // Получаем путь к файлу
        $cacheFile = $this->getCacheFile($key);

        // Если файл уже существует и принадлежит другому владельцу(owner)
        if (is_file($cacheFile) && function_exists('posix_geteuid') && fileowner($cacheFile) !== posix_geteuid()) {
            // Удаляем файл
            @unlink($cacheFile);
        }

        // Получаем дирректорию для хранения файла с кэшем
        $dir = DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, array_slice(explode(DIRECTORY_SEPARATOR, $cacheFile), 1, -1));

        // Если дирректория не была созданна ранее
        if(!is_dir($dir)){
            // Создаем дирректорию
           mkdir($dir, $this->dirsMode, true);
        }

        // Если существует дирректория и файл успешно записан
        if (is_dir($dir) && @file_put_contents($cacheFile, $value, LOCK_EX) !== false) {
            // Если указанны права которые необходимо устанавливать на файл
            if ($this->fileMode !== null) {
                // Задаем парава на файл
                @chmod($cacheFile, $this->fileMode);
            }
            // Если не переданно время жизни кэша
            if ($duration <= 0) {
                // Устанавниваем время жизни по умолчанию
                $duration = $this->defaultDuration;
            }

            // Устанавливаем время доступа к файлу
            return @touch($cacheFile, $duration + time());
        }

        // Если файл так и не удлось создать, получим последнюю ошибку
        $error = error_get_last();
        // Возвращаем исключение
        throw new Exception("Could not create cache file '{$cacheFile}': {$error['message']}");

        return false;
    }

    /**
     * Удаление кэша по ключу
     *
     * @param array|string $key
     * @param bool $expiredOnly
     * @return bool|mixed
     * @throws Exception
     */
    public function delete($key, $expiredOnly = false)
    {
        // Получаем путь к файлу для удаления
        $path = $this->getCacheFile($key);
        // Если файла не существует
        if(!is_file($path)){
            // Будем считать что передан путь к дирректории
            $path = $this->getCacheDir($key);
        }

        $this->clearCache($path, $expiredOnly);

        return true;
    }

    /**
     * @param bool $expiredOnly
     * @return bool|mixed
     * @throws Exception
     */
    public function flush($expiredOnly = false)
    {
        $this->cc(!$expiredOnly, $expiredOnly);

        return true;
    }

    /**
     * Проверяет существует ли актуальный кэш, по ключу $key
     * @param string|array $key
     * @return bool|mixed
     * @throws Exception
     */
    public function exists($key)
    {
        $cacheFile = $this->getCacheFile($key);

        return @filemtime($cacheFile) > time();
    }

    /**
     * Построение пути к фаилу с кэшом
     *
     * @param $key
     * @return bool|string
     * @throws Exception
     */
    private function getCacheFile($key){

        $path = $this->cachePath;
        // Если ключ обычная строка
        if(is_string($key)){
            // Считаем элемент именем файла
            $key = md5($key) . $this->cacheFileSuffix;

            // Дополняем путь
            $path .= DIRECTORY_SEPARATOR . $key;
        }elseif (is_array($key)){ // Если ключ является массивом

            // Перебираем массив, каждый элемент массива кроме последнего будет являться подпапкой
            foreach ($key as $item){
                // Если ключ является строкой
               if(is_string($item)){
                   // Очищаем строку от символов которые могут нарушить корректность пути
                   $item = preg_replace( '/[^A-Za-z0-9\_\-]/', '', $item );
                   // Если это последний элемент массива
                   if(!next($key)){
                       // Считаем элемент именем файла
                       $item = md5($item) . $this->cacheFileSuffix;
                   }

                   // Дополняем путь
                   $path .= DIRECTORY_SEPARATOR . $item;
               }elseif (is_array($item) or is_object($item)){ // Если ключ является массивом
                   // Преобразуем массив в json строку, и хэшируем в md5
                   $item = md5(json_encode($item));
                   // Если это последний элемент массива
                   if(!next($key)){
                       // Считаем элемент именем файла
                       $item .= $this->cacheFileSuffix;
                   }

                   // Дополняем путь
                   $path .= DIRECTORY_SEPARATOR . $item;
               }
            }
        }else{ // Если ключ не массив и не строка
            // Вернем исключение
            throw new Exception("Incorrect cache key type");
        }

        return $path;
    }

    /**
     * Получение поти к дирректории по ключу $key
     *
     * @param $key
     * @return string
     * @throws Exception
     */
    private function getCacheDir($key){

        $path = $this->cachePath;
        // Если ключ обычная строка
        if(is_string($key)){
            $path .= DIRECTORY_SEPARATOR . $key;
        }elseif (is_array($key)){ // Если ключ является массивом

            // Перебираем массив, каждый элемент массива кроме последнего будет являться подпапкой
            foreach ($key as $item){
                // Если ключ является строкой или числом
               if(is_string($item) or is_int($item)){
                   // Очищаем строку от символов которые могут нарушить корректность пути
                   $item = preg_replace( '/[^A-Za-z0-9\_\-]/', '', $item );
                   // Дополняем путь
                   $path .= DIRECTORY_SEPARATOR . $item;
               }elseif (is_array($item) or is_object($item)){ // Если ключ является массивом
                   // Преобразуем массив в json строку, и хэшируем в md5
                   $item = md5(json_encode($item));

                   // Дополняем путь
                   $path .= DIRECTORY_SEPARATOR . $item;
               }
            }
        }else{ // Если ключ не массив и не строка
            // Вернем исключение
            throw new Exception("Incorrect cache key type");
        }

        return $path . DIRECTORY_SEPARATOR;
    }

    /**
     * Очистка кэша по force / по вероятности попадания / просроченного
     *
     * @param bool $force
     * @param bool $expiredOnly
     * @throws Exception
     */
    private function cc($force = false, $expiredOnly = true)
    {
        // Если задан force режим или произошло попадание в вероятность
        if ($force || mt_rand(0, 1000000) < $this->ccProbability) {
            // Удаляем весь кэш
            $this->clearCache($this->cachePath, false);

        }elseif ($expiredOnly){ // Если необходимо удалить только кэш с законченым сроком действия
            // Удаляем весь кэш срок действия которого окончился
            $this->clearCache($this->cachePath, true);
        }
    }

    /**
     * Рекурсивное удаление кэша по указанному пути
     *
     * @param $path
     * @param $expiredOnly
     * @throws Exception
     */
    private function clearCache($path, $expiredOnly = true){

        // Если удалось открыть каталог
        if (($handle = opendir($path)) !== false) {
            // Пока в каталоге есть элементы
            while (($file = readdir($handle)) !== false) {
                if ($file[0] === '.') {
                    continue;
                }
                // путь к элементу каталога
                $fullPath = $path . DIRECTORY_SEPARATOR . $file;
                // Если элемент является каталогом
                if (is_dir($fullPath)) {
                    // Войдем в рекурсию
                    $this->clearCache($fullPath, $expiredOnly);

                    // Если не указанно удалять только просроченные элементы
                    if (!$expiredOnly) {
                        // Удаляем дирректорию, если удаление не удалось
                        if (!@rmdir($fullPath)) {
                            // Возвращаем исключение
                            $error = error_get_last();
                            throw new Exception("Unable to remove directory '{$fullPath}': {$error['message']}");
                        }
                    }
                } elseif (!$expiredOnly || $expiredOnly && @filemtime($fullPath) < time()) { // Если элемент является файлом
                    // Удаляем файл. Если не удалось удалить файл
                    if (!@unlink($fullPath)) {
                        // Возвращаем исключение
                        $error = error_get_last();
                        throw new Exception("Unable to remove file '{$fullPath}': {$error['message']}", __METHOD__);
                    }
                }
            }
            // Прерываем работу с каталогом
            closedir($handle);
        }
    }



}