<?php
namespace Icorelab\Cache;

/**
 * Проверка зависимости кэша от результата выполнения sql запроса
 *
 * Class DbDependency
 */
class DbDependency extends Dependency{

    protected $sql; // Запрос по которому будет проверяться состояние зависимости

    /**
     * Принимаем параметры необходимые получения текущего значениея зависимости
     *
     * DbDependency constructor.
     * @param array $params
     * @throws \Exception
     */
    public function __construct(array $params)
    {
        // Если передан параметр sql с запросом
        if(isset($params['sql']) && $params['sql']){
            $this->sql = $params['sql'];
        }else{ // Если парамерт sql не передан или пуст
            // Вернем исключение
            throw new \Exception('Parameter "sql" is required');
        }

    }

    /**
     * Получаем текущее значение выполнения запроса
     *
     * @param $cache
     * @return array
     */
    protected function generateDependencyData($cache){
        // Получаем текущую базу данных
        $db = ProjectSettings::GetDefaultDatabase();

        // Выполняем sql запрос
        $result = $db->query($this->sql)->fetchAll();

        return $result;
    }
}