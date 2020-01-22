<?php
namespace Icorelab\Cache;

/**
 * Created by PhpStorm.
 * User: vladimirc
 * Date: 6/6/19
 * Time: 11:34 PM
 */

/**
 * Interface CacheInterface
 */
interface CacheInterface{

    /**
     * Возвращает данные из кэша по ключу.
     * Если кэш не был найден возвращает значение $defaultValue,
     * Если значение $defaultValue не задано, вернет false
     *
     * @param string|array $key
     * @return mixed
     */
    public function getValue($key);

    /**
     * Сохраняет новое значение кэша по ключу
     *
     * @param string|array $key
     * @param $value
     * @param $duration
     * @return mixed
     */
    public function setValue($key, $value, $duration = 0);

    /**
     * Удаление кэша по ключу
     *
     * @param $key
     * @param bool $expiredOnly
     * @return mixed
     */
    public function delete($key, $expiredOnly = false);

    /**
     * Удаляет все просроченные данные, или все данные, в зависомости от флага $expiredOnly.
     *
     * @param bool $expiredOnly
     * @return mixed
     */
    public function flush($expiredOnly = false);

    /**
     * Есть ли указанный ключ в кэше;
     *
     * @param string|array $key
     * @return mixed
     */
    public function exists($key);
}