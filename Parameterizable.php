<?php

namespace App\Support;


use App\Enums\ParametersEnum;
use Carbon\Carbon;
use Illuminate\Support\Arr;

/**
 * Пример трейта
 */

trait Parameterizable
{
    /**
     * Установка параметра
     *
     * @param $name
     * @param $value
     * @param string $type
     */
    public function setParameter($name, $value, $type = ParametersEnum::DEFAULT)
    {
        if (!is_null($value)) {
            $param = $this->getParameter($name, $type);
            $param->value = $value;
            $param->save();
        } else {
            $this->removeParameter($name, $type);
        }
    }


    /**
     * Получение параметра
     *
     * @param $name
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function getParameter($name, $type = ParametersEnum::DEFAULT)
    {
        $param = $this->parameters->where('name', '=', $name)->where('type', '=', $type)->first();

        if (!$param) {
            $param = $this->parameters()->newModelInstance([
                'name' => $name,
                'type' => $type
            ]);
            // Связываем параметр с текущим объектом
            $param->associated()->associate($this);
            $param->setCreatedAt(Carbon::now());
            $param->setUpdatedAt(Carbon::now());

            // Убираем ключ из аттрибутов
            $param->offsetUnset('associated');
        }

        return $param;
    }


    /**
     * Добавление параметра
     *
     * @param $name
     * @param $value
     * @param string $type
     * @return false|\Illuminate\Database\Eloquent\Model
     */
    public function addParameter($name, $value, $type = ParametersEnum::DEFAULT)
    {
        $param = $this->parameters()->newModelInstance([
            'name' => $name,
            'value' => $value,
            'type' => $type
        ]);
        $param = $this->parameters()->save($param);

        return $param;
    }


    /**
     * Получение параметров
     *
     * @param $name
     * @param string $type
     * @return mixed
     */
    public function getParameters($name, $type = ParametersEnum::DEFAULT)
    {
        $param = $this->parameters
            ->where('name', '=', $name)
            ->where('type', '=', $type);

        return $param;
    }


    public function setArrayParameters(array $data)
    {
        $params = $this->parameters;

        // Обновление изменившихся данных
        foreach ($data as $type => $values) {
            // Получаем только параметры с нужным типом
            $params->where('type', '=', $type)->filter(function ($item) use ($values) {
                // Фильтрация по названию
                return in_array($item->name, array_keys($values));
            })->filter(function ($item) use ($values) {
                // Фильтрация по несовпадающим значениеям
                $value = Arr::get($values, $item->key);
                return $value != $item->value;
            })->each(function ($item) use ($values, $type) {
                // Обновлям значения для параметров с одинковыми ключами, но разными значениями
                $key = $item->name;
                $value = Arr::get($values, $key);
                $this->setParameter($key, $value, $type);
            });
        }


        // Вставка данных, которых раньше не было
        $insertData = [];
        foreach ($data as $type => $values) {
            // Убираем из вставки значения с null
            $values = array_filter($values, function ($item) {
                return !is_null($item);
            });

            // Находим значения ключей, которые необходимо вставить
            $keys = array_keys($values);
            $paramsNames = $params->where('type', '=', $type)->pluck('name');
            $insertKeys = array_diff($keys, $paramsNames->toArray());

            foreach ($insertKeys as $key) {
                // Получаем объект с заполненными свойствами
                $insertParam = $this->getParameter($key, $type);
                $insertParam->value = Arr::get($values, $key);
                $insertData[] = $insertParam->toArray();
            }
        }
        $this->parameters()->newQuery()->insert($insertData);

        $this->load('parameters');
    }


    /**
     * Удаление параметра
     *
     * @param $name
     * @param string $type
     */
    public function removeParameter($name, $type = ParametersEnum::DEFAULT)
    {
        $this->parameters()
            ->where('name', '=', $name)
            ->where('type', '=', $type)
            ->delete();
    }


    /**
     * Свзяь с таблицей параемтров
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function parameters()
    {
        return $this->hasMany('');
    }
}
