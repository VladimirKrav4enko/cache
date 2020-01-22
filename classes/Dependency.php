<?php
namespace Icorelab\Cache;

/**
 * Абстрактный класс для классов зависимостей кэша
 *
 * Class Dependency
 */
abstract class Dependency{

    public $data;   // Данные зависимости, которые сохраняются в кэше и потом сравниваются с новым значением

    /**
     * В дочернем от Dependency классе обязательно должен быть объявлен конструктор
     * принимающий параметры проверки зависимости кэша
     *
     * Dependency constructor.
     * @param array $params
     */
    abstract public function __construct(array $params);

    /**
     * Заносим в $this->data начальное значение зависимости
     *
     * @param $cache
     */
    public function evaluateDependency($cache){
        $this->data = $this->generateDependencyData($cache);
    }

    /**
     * Проверяем изменилась ли зависимость
     *
     * @param $cache
     * @return bool
     */
    public function isChanged($cache){
        // Получаем текущее значение зависимости
        $data = $this->generateDependencyData($cache);

        // Сравниваем текущее значение зависимости, с сохраненным ранее, и возвращаем результат
        return $data !== $this->data;
    }

    /**
     * Вычисляем текущее значение зависимости
     *
     * @param $cache
     */
    abstract protected function generateDependencyData($cache);

}