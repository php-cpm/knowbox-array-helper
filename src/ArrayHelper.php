<?php

namespace Knowbox\Libs;

use Closure;
use \yii\helpers\ArrayHelper as YiiArrayHelper;

/**
 * Class ArrayHelper
 * @package Knowbox\Libs
 * @author Knowbox-dev
 */
class ArrayHelper extends YiiArrayHelper
{
    /**
     * -------------------------------------------------------------
     * 多维数组根据某一个字段去重。尤其适用于对于 ORM 取出来的结果集
     * -------------------------------------------------------------
     * 1.如果有多个元素重复，只会保留第一个元素的值。
     * ----------------------------------------------
     * 原始数组:
     * [
     *   ['name' => 'iPhone 6', 'brand' => 'Apple', 'type' => 'phone'],
     *   ['name' => 'iPhone 5', 'brand' => 'Apple', 'type' => 'phone'],
     *   ['name' => 'Apple Watch', 'brand' => 'Apple', 'type' => 'watch'],
     *   ['name' => 'Galaxy S6', 'brand' => 'Samsung', 'type' => 'phone'],
     *   ['name' => 'Galaxy Gear', 'brand' => 'Samsung', 'type' => 'watch'],
     * ]
     *
     * 如果按照name进行 Unique 去重，那么结果集：
     * [
     *   ['name' => 'iPhone 6', 'brand' => 'Apple', 'type' => 'phone'],
     *   ['name' => 'Galaxy S6', 'brand' => 'Samsung', 'type' => 'phone'],
     * ]
     *
     * @param $array
     * @param $uniquedKey
     *
     * @return mixed
     * @throws \Exception
     */
    public static function uniqueBy(array $array, $uniquedKey)
    {
        if (empty($uniquedKey)) {
            return $array;
        }

        //已经出现的值
        $exists = [];
        $uniquedArray = array_filter($array, function ($value) use (&$exists, $uniquedKey) {
            //如果数组元素中没有这个这个key，直接报错
            if (! isset($value[$uniquedKey])) {
                throw new \Exception("{$uniquedKey} not existed in array");
            }
            //需要被去重字段的值，比如说：iphone6
            $iteratedArrayValue = $value[$uniquedKey];
            //如果这个值已经存在了，直接返回 false，不让他继续被加入数据
            if (in_array($iteratedArrayValue, $exists)) {
                return false;
            }
            //把这个值加入容器，以便去重
            $exists[] = $iteratedArrayValue;

            return true;
        });

        //array_filter之后，数组的 key 可能会变得不连续，这个时候如果 json_encode,那么会变成字典集合，比如 [ 0=>{},5=>{} ]
        //但是客户端不要这种值，他们只要数组中的 obj，所以这个时候必须保证这个去重后的数组key 是连续整数。所以调用 array_values
        return array_values($uniquedArray);
    }

    /**
     * -------------------------------------------------------------------------------
     * 使用指定的数组 key，来对二维数组进行分组。
     * -------------------------------------------------------------------------------
     *
     * 原始数组：
     * [
     *   ['name' => 'iPhone 6', 'brand' => 'Apple', 'type' => 'phone'],
     *   ['name' => 'iPhone 5', 'brand' => 'Apple', 'type' => 'phone'],
     *   ['name' => 'Apple Watch', 'brand' => 'Apple', 'type' => 'watch'],
     *   ['name' => 'Galaxy S6', 'brand' => 'Samsung', 'type' => 'phone'],
     *   ['name' => 'Galaxy Gear', 'brand' => 'Samsung', 'type' => 'watch'],
     * ]
     *
     * Then the method will group by the array elements, and group them by the given key.
     * And will return like that.
     *
     * [
     *    [
     *      'brand' => 'Apple',
     *      'data' => [
     *          ['name' => 'iPhone 6', 'brand' => 'Apple', 'type' => 'phone'],
     *          ['name' => 'iPhone 5', 'brand' => 'Apple', 'type' => 'phone'],
     *          ['name' => 'Apple Watch', 'brand' => 'Apple', 'type' => 'watch'],
     *      ]
     *     ],
     *   [
     *      'brand' => 'Samsung',
     *      'data' => [
     *          ['name' => 'Galaxy S6', 'brand' => 'Samsung', 'type' => 'phone'],
     *          ['name' => 'Galaxy Gear', 'brand' => 'Samsung', 'type' => 'watch'],
     *      ]
     *    ]
     * ]
     * ]
     *
     * @param array $array 要被分组的数组
     * @param string $groupKey 分组的依据，是原有数组的某一个列
     * @param string $inGroupUniqueKey 分组去重的依据，是原有数组的一个列 ,用来保证在分组后的 data 小分组里没有重复的值
     * @param callable|null $callback
     *
     * @return array
     * @throws \Exception
     */
    public static function groupBy($array, $groupKey, $inGroupUniqueKey = null, $callback = null)
    {
        $result = [];
        foreach ($array as $item) {
            //如果原始数组元素没有这个分组用的 key，直接报错
            if (! isset($item[$groupKey])) {
                throw new \Exception("{$groupKey} must in the array");
            }

            //搜索需要被放入的分组的索引值，根据 【分组值】去查询
            $shouldGroupIndex = array_search($item[$groupKey], array_column($result, $groupKey));

            //如果用户给定了回调函数，使用回调函数处理结果
            if (is_callable($callback)) {
                $item = call_user_func($callback, $item);
            }

            //新增的处理，如果这个分组是空的，不加入最终结果
            if (empty($item)) {
                continue;
            }

            //如果这个数组元素没有在分组元素中出现,添加到结果集里
            if ($shouldGroupIndex === false) {
                array_push($result, [
                    $groupKey => $item[$groupKey],
                    'data' => [$item],
                ]);
                continue;
            }

            //如果这个数组元素对应的分组已经构建，那么把她加入该分组中的 data 小分组
            //如果没有指定小分组内部的唯一 key，直接把这个元素加入这个
            if (is_null($inGroupUniqueKey)) {
                array_push($result[$shouldGroupIndex]['data'], $item);
                continue;
            }

            //如果指定了小分组内部的唯一 key，判断钙元素不重复
            if (false === array_search($item[$inGroupUniqueKey],
                    array_column($result[$shouldGroupIndex]['data'], $inGroupUniqueKey))) {
                array_push($result[$shouldGroupIndex]['data'], $item);
                continue;
            }
        }

        return $result;
    }

    /**
     * -------------------------------------------------------------------------
     * 合并两个多维数组，使用一个类似 sql 的 inner join 语句
     * -------------------------------------------------------------------------
     * 原始数组：
     * [
     *  [ 'user_id' => 1, 'name' => 'Hello world'],
     *  [ 'user_id' => 2, 'name' => 'Hello world2'],
     *  [ 'User_id' => 3, 'name' => 'Hello world3'],
     * ]
     * -----------------------------------------------------------------------
     * 要合并进去的数组:
     * [
     *  [ 'id' => 1, 'name' => 'Hello world', 'avatar' => 'xxx', 'grade' => 1],
     *  [ 'id' => 2, 'name' => 'Hello world2', 'avatar' => 'xxx', 'grade' => 2],
     *  [ 'id' => 3, 'name' => 'Hello world3', 'avatar' => 'xxx', 'grade' => 3],
     *  [ 'id' => 4, 'name' => 'Hello world4', 'avatar' => 'xxx', 'grade' => 4],
     * ]
     * -----------------------------------------------------------------------
     * 合并条件:意思是用第一个数组的 product_id 的值和第二个数组的 book_id 的值进行对比,
     * 如果相等，就把这两个数组对应位置的元素进行 array_merge
     * ----------------------------------------------------
     * [ 'product_id' => 'book_id' ]
     * ----------------------------------------------------
     * 结果数组：
     * [
     *  [ 'user_id'=> 1, 'id' => 1, 'name' => 'Hello world', 'avatar' => 'xxx', 'grade' => 1],
     *  [ 'user_id'=> 2, 'id' => 2, 'name' => 'Hello world2', 'avatar' => 'xxx', 'grade' => 2],
     *  [ 'user_id'=> 3,'id' => 3, 'name' => 'Hello world3', 'avatar' => 'xxx', 'grade' => 3],
     *  [ 'user_id'=> 4,'id' => 4, 'name' => 'Hello world4', 'avatar' => 'xxx', 'grade' => 4],
     * ]
     *
     * @param array $originalArray 原始数组
     * @param array $mergedArray 要合并进去的数组
     * @param array|string $condition 用来寻找合并元素的条件,类似 sql 的 where
     * @param Closure|null $closure 可提供的闭包，用来对合并时候的新数组元素进行处理，主要处理一些需要把数组 key 转换的时候，或者新增键值对
     *
     * @return array
     * @throws \Exception
     */
    public static function leftJoin(array $originalArray, array $mergedArray, $condition, closure $closure = null)
    {
        $result = [];
        if (is_array($condition)) {
            $originalKey = key($condition);  //原始数组中用来查询的 key 名字
            $mergedKey = current($condition); //要合并数组中用来查询的 key 名字
        } elseif (is_string($condition)) {
            //如果传递的是一个字符串，就相当于是两个相同的 key
            $originalKey = $mergedKey = $condition;
        } else {
            throw new \Exception("关联条件只能是数组或者字符串");
        }

        //遍历原始数组，在要 join 的数组中查询
        foreach ($originalArray as $originalItem) {
            //是否找到匹配的元素，用来控制只合并一次，不重复合并元素
            $hadFound = false;

            //在要合并的数组中进行查询
            foreach ($mergedArray as $mergeItem) {
                //在要合并的数组中，只进行一次合并操作，如果找到了第一个匹配元素，之后就不在进行查询
                if ($hadFound) {
                    continue;
                }
                //如果两个对比的元素没有相同的，判断下一个元素
                if ($mergeItem[$mergedKey] != $originalItem[$originalKey]) {
                    continue;
                }

                //执行到这里的时候，代表找到了join 条件匹配的元素，这个时候如果传递了闭包，对要合并进去的数组进行处理
                if (! is_null($closure)) {
                    $mergeItem = $closure($mergeItem);
                }

                //合并两个匹配的数组元素，同时添加到结果集
                $result[] = array_merge($originalItem, $mergeItem);

                //标志位已经找到匹配元素
                $hadFound = true;
            }
            //如果没找到合并元素,还是要把这个原始元素添加到结果集里，否则如果这个元素一个匹配的都没有找到，最后就一堆 continue，最终将在原始数组中失去她
            if (false === $hadFound) {
                $result [] = $originalItem;
            }
        }

        return $result;
    }

    /**
     * -------------------------------------------------------------------------
     * 合并两个多维数组，使用一个类似 sql 的 inner join 语句
     * -------------------------------------------------------------------------
     * 与普通的 leftJoin 不同的是，这个方法只会把匹配的两个元素都传入闭包中，交由客户端自请处理
     * 原始数组：
     * [
     *  [ 'user_id' => 1, 'name' => 'Hello world'],
     *  [ 'user_id' => 2, 'name' => 'Hello world2'],
     *  [ 'User_id' => 3, 'name' => 'Hello world3'],
     * ]
     * -----------------------------------------------------------------------
     * 要合并进去的数组:
     * [
     *  [ 'id' => 1, 'name' => 'Hello world', 'avatar' => 'xxx', 'grade' => 1],
     *  [ 'id' => 2, 'name' => 'Hello world2', 'avatar' => 'xxx', 'grade' => 2],
     *  [ 'id' => 3, 'name' => 'Hello world3', 'avatar' => 'xxx', 'grade' => 3],
     *  [ 'id' => 4, 'name' => 'Hello world4', 'avatar' => 'xxx', 'grade' => 4],
     * ]
     * -----------------------------------------------------------------------
     * 合并条件:意思是用第一个数组的 product_id 的值和第二个数组的 book_id 的值进行对比,
     * 如果相等，就把这两个数组对应位置的元素进行 array_merge
     * ----------------------------------------------------
     * [ 'product_id' => 'book_id' ]
     * ----------------------------------------------------
     *
     * @param array $originalArray 原始数组
     * @param array $mergedArray 要合并进去的数组
     * @param array|string $condition 用来寻找合并元素的条件,类似 sql 的 where
     * @param Closure $closure 用来对匹配到的两个元素进行处理，第一个参数是原始数组中的元素，第二个参数是要合并数组中的匹配元素，或者 null
     *
     * @return array
     * @throws \Exception
     */
    public static function userLeftJoin(array $originalArray, array $mergedArray, $condition, closure $closure)
    {
        $result = [];
        if (is_array($condition)) {
            $originalKey = key($condition);  //原始数组中用来查询的 key 名字
            $mergedKey = current($condition); //要合并数组中用来查询的 key 名字
        } elseif (is_string($condition)) {
            //如果传递的是一个字符串，就相当于是两个相同的 key
            $originalKey = $mergedKey = $condition;
        } else {
            throw new \Exception("关联条件只能是数组或者字符串");
        }

        //遍历原始数组，在要 join 的数组中查询
        foreach ($originalArray as $originalItem) {
            //是否找到匹配的元素，用来控制只合并一次，不重复合并元素
            $hadFound = false;
            //在要合并的数组中进行查询
            foreach ($mergedArray as $mergeItem) {
                //在要合并的数组中，只进行一次合并操作，如果找到了第一个匹配元素，之后就不在进行查询
                if ($hadFound) {
                    continue;
                }
                //如果两个对比的元素没有相同的，判断下一个元素
                if ($mergeItem[$mergedKey] != $originalItem[$originalKey]) {
                    continue;
                }

                //执行到这里的时候，代表找到了join 条件匹配的元素，传递了两个匹配的元素进入闭包
                $result[] = call_user_func($closure, $originalItem, $mergeItem);

                //标志位已经找到匹配元素
                $hadFound = true;
            }
            //如果没找到合并元素,还是要把这个原始元素添加到结果集里，否则如果这个元素一个匹配的都没有找到，最后就一堆 continue，最终将在原始数组中失去她
            //这个时候用 null 来代替没有匹配到的元素，用于对某些时候需要补充 default 字段的时候用.
            if (false === $hadFound) {
                $result [] = call_user_func($closure, $originalItem, null);
            }
        }

        return $result;
    }

    /**
     * -------------------------------------------------------------------------
     * 合并两个多维数组，使用一个类似 sql 的 inner join 语句
     * -------------------------------------------------------------------------
     * 与普通的 leftJoin 不同的是，这个方法只会把匹配的两个元素都传入闭包中，交由客户端自请处理
     * ----------------------------------------------------
     *
     * @param array $originalArray 原始数组
     * @param array $multipleMergedArray 要合并的多个多维数组
     * @param array|string $commonArrayKey 用来查询匹配条件的数组 key，需要保证要合并的多个多维数组中都有这个 key
     * @param Closure $closure 用来对匹配到的两个元素进行处理，第一个参数是原始数组中的元素，第二、三...个参数是要合并数组中的匹配元素，或者 null
     *
     * @return array
     * @throws \Exception
     */
    public static function userLeftJoinMultiple(
        array $originalArray,
        array $multipleMergedArray,
        $commonArrayKey,
        closure $closure
    ) {
        //如果元是数组时空，直接返回空数组
        if (empty($originalArray)) {
            return [];
        }
        $result = [];
        //判断输入参数必须有公用的 key
        if (! isset(current($originalArray)[$commonArrayKey])) {
            throw new \Exception("原始数组必须都有公用的数组 key");
        }
        //处理多个要合并的数组，首先保证每一个数组都有公用的 key，然后把每一个数组列表按照要合并的 key重建索引
        foreach ($multipleMergedArray as &$value) {
            if (! empty($value) && ! isset(current($value)[$commonArrayKey])) {
                throw new \Exception(sprintf("要合并的多个数组必须都有公用的数组 key。数组:%s，缺少该key", json_encode($value)));
            }
            $value = array_column($value, null, $commonArrayKey);
        }
        //遍历原始数组，在要 join 的数组中查询
        foreach ($originalArray as $originalItem) {
            $parameters = [$originalItem];
            //原始数组中要匹配的 key 的值
            $originItemValue = $originalItem[$commonArrayKey];
            foreach ($multipleMergedArray as $mergedArray) {
                $parameters[] = $mergedArray[$originItemValue] ?? null;
            }
            $result [] = call_user_func($closure, ...$parameters);
        }

        return $result;
    }

    /**
     * ---------------------------------------------------------------------------------------
     * 获取数组某个位置之后的 N 个元素，尤其适用于那些配置数组，因为他们的 key 都不是连续的。不能使用+1这种操作
     * -----------------------------------------------------------------------------------------
     * $array = [
     *  100 => '日新月异',
     *  200 => '开天辟地',
     *  800 => '毁天灭地',
     *  1600 => '奥手动你',
     * ];
     * 调用该方法后:
     *  $result = getNextByValue($array,'开天辟地',1)
     * 返回结果： '毁天灭地'
     *
     * @param array $array 要搜索的数组
     * @param string|int $search 要搜索的元素的值
     * @param int $offset 如果偏移量是复数，那么向后搜索；反之，向前搜索。
     *
     * @return bool|mixed
     */
    public static function getNextByValue($array, $search, $offset = 1)
    {
        //遍历数组，找到目标位置
        while ($item = current($array)) {
            //如果不是要找到的数组，遍历下一个位置
            if ($item != $search) {
                //移动数组内部指针到下一个位置
                next($array);
                //跳过这一次的循环
                continue;
            }
            //找到指定的元素后，开始向后遍历 N 个位置
            for ($i = 0; $i < abs($offset); $i++) {
                //如果 Offset 是负数，那么向前遍历，反之向后遍历
                ($offset < 0) ? prev($array) : next($array);
            }

            //这个时候不需要判断指针是否越界了，current 函数会在越界后返回 false
            return current($array);
        }

        //如果没有找到，那么返回 false
        return false;
    }

    /**
     * --------------------------------------------------------------------------------
     * 在一个数组池中根据键值对应关系，寻找一个对应的数组元素，进行数组 merge
     * --------------------------------------------------------------------------------
     * A 数组：
     *     [ 'book_id' => 1, 'name'=> 'qwe', 'title' => 'qweqweqwe', 'author'=> 'qwe']
     * B数组:
     * [
     *     [ 'bookId' => 1, 'Name' => 'qwe', 'country' => 'xxxx', 'rank' => 'qweqwe']
     *     [ 'bookId' => 2 'Name' => 'qwe', 'country' => 'xxxx', 'rank' => 'qweqwe']
     *     [ 'bookId' => 3, 'Name' => 'qwe', 'country' => 'xxxx', 'rank' => 'qweqwe']
     * ]
     * 结果:
     *
     *     [ 'book_id' => 1, 'name'=> 'qwe', 'title' => 'qweqweqwe', 'author'=> 'qwe',
     *       'bookId' => 1, 'Name' => 'qwe', 'country' => 'xxxx', 'rank' => 'qweqwe'
     *      ]
     *
     * --------------------------------------------------------------------------------
     * @param array $originalData
     * @param array $sourceDataArray
     * @param array $condition
     * @param bool $fillDefaultValue
     *
     * @return array
     */
    public static function joinByKey(
        array $originalData,
        array $sourceDataArray,
        array $condition,
        $fillDefaultValue = false
    ) {
        $originalKey = key($condition);
        $mergedKey = current($condition);
        $sourceDataKeys = [];
        $hadFoundMappingData = false;

        foreach ($sourceDataArray as $sourceData) {
            $sourceDataKeys = array_keys($sourceData);

            //数据池中的 key 不存在
            if (! isset($sourceData[$mergedKey])) {
                continue;
            }
            //值对应
            if ($sourceData[$mergedKey] == $originalData[$originalKey]) {
                $originalData = array_merge($originalData, $sourceData);
                $hadFoundMappingData = true;
                break;
            }
        }
        //如果没有找到对应的值，并且需要填充默认值
        if (! $hadFoundMappingData && $fillDefaultValue) {
            foreach ($sourceDataKeys as $sourceDataKey) {
                if (isset($originalData[$sourceDataKey])) {
                    continue;
                }
                $originalData[$sourceDataKey] = '';
            }
        }

        return $originalData;
    }

    //打乱二维数组
    public static function shuffleAssoc($list)
    {
        if (! is_array($list)) {
            return $list;
        }
        $keys = array_keys($list);
        shuffle($keys);
        $randomArray = [];
        foreach ($keys as $key) {
            $randomArray[$key] = $list[$key];
        }

        return $randomArray;
    }

    /**
     * ------------------------------------------------
     * 把指定数据添加到原始数组中指定的位置
     * ------------------------------------------------
     * 1.这个方法会直接修改原始数组
     * 2.默认会添加到指定位置的名为 data 的数组中
     * -------------------------------
     * 原数据：
     * [
     *      [
     *          'name' => '上帝',
     *          'data' => []
     *      ],
     *      [
     *          'name' => '恶魔',
     *          'data' => []
     *      ],
     * ]
     * 我们调用该方法：
     *    appendWithFieldAndName ($result,['name'=>'上帝'], $data)
     * 之后得到结果:
     * [
     *      [
     *          'name' => '上帝',
     *          'data' => [ $data ,]
     *      ],
     *      [
     *          'name' => '恶魔',
     *          'data' => []
     *      ],
     * ]
     *
     * @param array $result
     * @param       $condition
     * @param       $data
     *
     * @return bool
     */
    public static function appendWithFieldAndName(array &$result, array $condition, $data)
    {
        if (empty($condition)) {
            return false;
        }
        //要搜索的 key
        $field = key($condition);
        //要搜索的值
        $value = current($condition);
        //搜索要插入的索引
        $index = array_search($value, array_column($result, $field));
        //如果找不到要插入的节点，直接返回
        if (false === $index) {
            return false;
        }
        //添加到这个位置指定的 key
        array_push($result[$index]['data'], $data);

        return true;
    }

    /**
     * 专门处理这种，需要 merge 好多个数组的时候,当我们需要合并数组
     * [
     *     "questionPacks" : [  //所选的题包数据,如果某一个题包没有选择，就不需要添加到 json 里
     *      [
     *        "questionPackId" : xxx,                //所选题包 ID
     *        "questionIds" [ 111,222,333,444]  //所选该题包下的题目列表
     *      ],
     *      [
     *        "questionPackId" : xxx,                //所选题包 ID
     *        "questionIds" [ 111,222,333,444]  //所选该题包下的题目列表
     *      ],
     *     ]
     * ]
     *
     * @param $array
     * @param $field
     *
     * @return array
     */
    public static function mergeMultiple($array, $field)
    {
        if (array_key_exists($field, $array)) {
            return $array;
        }
        $result = [];

        foreach ($array as $value) {
            $result = array_merge($value[$field]);
        }

        return array_values(array_unique($result));
    }

    /**
     * ------------------------------------------------
     * 使用闭包函数处理数组中的每一个元素
     * -------------------------------------------------
     * 1.该方法会直接修改原始数组
     * 2.在闭包中返回的结果，将被array_merge到原始数组元素上
     *
     * @param array $array
     * @param Closure $closure
     */
    public static function addNewColumn(array &$array, closure $closure)
    {
        foreach ($array as &$item) {
            //如果是传入的多维数组，那么执行合并操作
            if (is_array($item)) {
                $item = array_merge($item, call_user_func($closure, $item));
            } else {
                //如果传入的是低维数组，执行替换操作
                $item = call_user_func($closure, $item);
            }
        }
    }

    /**
     * 修改每一行
     *
     * @param array $array
     * @param Closure $closure
     */
    public static function modifyColumn(array &$array, closure $closure)
    {
        foreach ($array as &$item) {
            $item = call_user_func($closure, $item);
        }
    }

    /**
     * ------------------------------------------------------------------------
     * 对分组形式的数据加新字段
     * ------------------------------------------------------------------------
     * 比如:
     * [
     * [
     *   "firstLevelName" => "笔试部分",
     *   "list" => [
     *         [
     *           "secondLevelName" => "听力判断题",
     *           "list" => [
     *              ['id'=>1,'name'=>'qwe'],
     *              ['id'=>1,'name'=>'qwe'],
     *         ],
     *         [
     *           "secondLevelName" => "听力判断题",
     *           "list" => [
     *              ['id'=>1,'name'=>'qwe'],
     *              ['id'=>1,'name'=>'qwe'],
     *         ],
     *    ],
     * ],
     * [
     *   "firstLevelName" => "笔试部分",
     *   "list" => [
     *         [
     *           "secondLevelName" => "听力判断题",
     *           "list" => [
     *              ['id'=>1,'name'=>'qwe'],
     *              ['id'=>1,'name'=>'qwe'],
     *         ],
     *         [
     *           "secondLevelName" => "听力判断题",
     *           "list" => [
     *              ['id'=>1,'name'=>'qwe'],
     *              ['id'=>1,'name'=>'qwe'],
     *         ],
     *    ],
     * ]
     * ]
     * 这种结构的数据，有时候往往需要对这些分组添加统计数据，所以我们要添加这种统计方法
     *
     * @param array $array 要处理的某一层数据
     * @param int $wantedDepth 要统计的递归深度
     * @param Closure|null $closure 要处理某一层数据的闭包
     * @param int $currentDepth 当前递归深度
     *
     * @return bool
     */
    public static function appendGroupInfoRecursive(
        array &$array,
        int $wantedDepth,
        closure $closure = null,
        $currentDepth = 1
    ) {
        if ($wantedDepth == $currentDepth) {
            foreach ($array as &$item) {
                $item = call_user_func($closure, $item);
            }

            return true;
        } //如果不到这个层级，把数组所有的的列表传入下一个层级
        elseif ($currentDepth < $wantedDepth) {
            foreach ($array as &$item) {
                //如果还没有到达递归深度，把当前的元素的 list 继续递归
                self::appendGroupInfoRecursive($item['list'], $wantedDepth, $closure, $currentDepth + 1);
            }
        } //如果递归遍历深度超过了指定深度，直接返回,确保不会无意义递归
        else {
            return false;
        }

        return true;
    }

    /**
     * --------------------------------
     * 添加带排序的索引值
     * --------------------------------
     *
     * @param $result
     */
    public static function appendOrderIndex(array &$result)
    {
        $index = 1;
        foreach ($result as &$item) {
            $item['orderIndex'] = $index++;
        }
    }

    /***
     * -------------------------------------------------------
     * 类似array_column，取出多个数组的列
     * -------------------------------------------------------
     * [
     *   ['id'=>1,'name'=>'张三','studentNo'=>'xxx',]
     *   ['id'=>2,'name'=>'李四','studentNo'=>'xxx',]
     *   ['id'=>3,'name'=>'王五','studentNo'=>'xxx',]
     * ]
     *
     * $result = ArrayHelper::columns(['id','studentNo']);
     *
     * //结果集合:
     * [
     *   ['id'=>1,'studentNo'=>'xxx',]
     *   ['id'=>2,'studentNo'=>'xxx',]
     *   ['id'=>3,'studentNo'=>'xxx',]
     * ]
     *
     * @param array $array
     * @param array $fields
     *
     * @return array
     */
    public static function columns(array $array, array $fields)
    {
        return array_map(function ($element) use ($fields) {
            $result = [];
            foreach ($fields as $field) {
                $result[$field] = $element[$field] ?? '';
            }

            return $result;
        }, $array);
    }

    /**
     * ----------------------------------------
     * 语法糖，分组然后重新映射 key
     * ----------------------------------------
     *
     * @param  array $array
     * @param  array $groupKeys
     *
     * @return array
     */
    public static function indexAndMapField(array $array, array $groupKeys)
    {
        $result = self::index($array, null, $groupKeys);

        return self::keyToField($result, $groupKeys);
    }

    /**
     * Indexes and/or groups the array according to a specified key.
     * The input should be either multidimensional array or an array of objects.
     *
     * The $key can be either a key name of the sub-array, a property name of object, or an anonymous
     * function that must return the value that will be used as a key.
     *
     * $groups is an array of keys, that will be used to group the input array into one or more sub-arrays based
     * on keys specified.
     *
     * If the `$key` is specified as `null` or a value of an element corresponding to the key is `null` in addition
     * to `$groups` not specified then the element is discarded.
     *
     * For example:
     *
     * ```php
     * $array = [
     *     ['id' => '123', 'data' => 'abc', 'device' => 'laptop'],
     *     ['id' => '345', 'data' => 'def', 'device' => 'tablet'],
     *     ['id' => '345', 'data' => 'hgi', 'device' => 'smartphone'],
     * ];
     * $result = ArrayHelper::index($array, 'id');
     * ```
     *
     * The result will be an associative array, where the key is the value of `id` attribute
     *
     * ```php
     * [
     *     '123' => ['id' => '123', 'data' => 'abc', 'device' => 'laptop'],
     *     '345' => ['id' => '345', 'data' => 'hgi', 'device' => 'smartphone']
     *     // The second element of an original array is overwritten by the last element because of the same id
     * ]
     * ```
     *
     * An anonymous function can be used in the grouping array as well.
     *
     * ```php
     * $result = ArrayHelper::index($array, function ($element) {
     *     return $element['id'];
     * });
     * ```
     *
     * Passing `id` as a third argument will group `$array` by `id`:
     *
     * ```php
     * $result = ArrayHelper::index($array, null, 'id');
     * ```
     *
     * The result will be a multidimensional array grouped by `id` on the first level, by `device` on the second level
     * and indexed by `data` on the third level:
     *
     * ```php
     * [
     *     '123' => [
     *         ['id' => '123', 'data' => 'abc', 'device' => 'laptop']
     *     ],
     *     '345' => [ // all elements with this index are present in the result array
     *         ['id' => '345', 'data' => 'def', 'device' => 'tablet'],
     *         ['id' => '345', 'data' => 'hgi', 'device' => 'smartphone'],
     *     ]
     * ]
     * ```
     *
     * The anonymous function can be used in the array of grouping keys as well:
     *
     * ```php
     * $result = ArrayHelper::index($array, 'data', [function ($element) {
     *     return $element['id'];
     * }, 'device']);
     * ```
     *
     * The result will be a multidimensional array grouped by `id` on the first level, by the `device` on the second one
     * and indexed by the `data` on the third level:
     *
     * ```php
     * [
     *     '123' => [
     *         'laptop' => [
     *             'abc' => ['id' => '123', 'data' => 'abc', 'device' => 'laptop']
     *         ]
     *     ],
     *     '345' => [
     *         'tablet' => [
     *             'def' => ['id' => '345', 'data' => 'def', 'device' => 'tablet']
     *         ],
     *         'smartphone' => [
     *             'hgi' => ['id' => '345', 'data' => 'hgi', 'device' => 'smartphone']
     *         ]
     *     ]
     * ]
     * ```
     *
     * @param array $array the array that needs to be indexed or grouped
     * @param string|\Closure|null $key the column name or anonymous function which result will be used to index the array
     * @param string|string[]|\Closure[]|null $groups the array of keys, that will be used to group the input array
     *                                                by one or more keys. If the $key attribute or its value for the particular element is null and
     *                                                $groups is not defined, the array element will be discarded. Otherwise, if $groups is
     *                                                specified, array element will be added to the result array without any key. This parameter is
     *                                                available since version 2.0.8.
     *
     * @return array the indexed and/or grouped array
     */
    public static function index($array, $key, $groups = [])
    {
        $result = [];
        $groups = (array)$groups;
        foreach ($array as $element) {
            $lastArray = &$result;
            foreach ($groups as $group) {
                $value = trim(static::getValue($element, $group));
                if (! array_key_exists($value, $lastArray)) {
                    $lastArray[$value] = [];
                }
                $lastArray = &$lastArray[$value];
            }
            if ($key === null) {
                if (! empty($groups)) {
                    $lastArray[] = $element;
                }
            } else {
                $value = trim(static::getValue($element, $key));
                if ($value !== null) {
                    if (is_float($value)) {
                        $value = StringHelper::floatToString($value);
                    }
                    $lastArray[$value] = $element;
                }
            }
            unset($lastArray);
        }

        return $result;
    }

    /**
     * 把二维数组弄平整,使用 ArrayHelper 的 index 分组方法把数组分组之后，可能会出现
     * [
     *   "听力部分" => [
     *        "听力填空题" => [
     *              ['id'=>1,'name'=>'qwe'],
     *              ['id'=>1,'name'=>'qwe'],
     *         ],
     *        "听力判断题" => [
     *              ['id'=>1,'name'=>'qwe'],
     *              ['id'=>1,'name'=>'qwe'],
     *         ],
     *        "听力选择题" => [
     *              ['id'=>1,'name'=>'qwe'],
     *              ['id'=>1,'name'=>'qwe'],
     *         ],
     *    ],
     *   "笔试部分" => [
     *        "判断题" => [
     *              ['id'=>1,'name'=>'qwe'],
     *              ['id'=>1,'name'=>'qwe'],
     *         ],
     *        "选择题" => [
     *              ['id'=>1,'name'=>'qwe'],
     *              ['id'=>1,'name'=>'qwe'],
     *         ],
     *    ],
     * ]
     * -----------------------------------------------------------
     * 变成如下:
     * [
     *   "firstLevelName" => "听力部分",
     *   "data" => [
     *         [
     *           "secondLevelName" => "听力填空题",
     *           "data" => [
     *              ['id'=>1,'name'=>'qwe'],
     *              ['id'=>1,'name'=>'qwe'],
     *         ],
     *         [
     *           "secondLevelName" => "听力判断题",
     *           "data" => [
     *              ['id'=>1,'name'=>'qwe'],
     *              ['id'=>1,'name'=>'qwe'],
     *         ],
     *    ],
     * ],
     * [
     *   "firstLevelName" => "笔试部分",
     *   "data" => [
     *         [
     *           "secondLevelName" => "听力判断题",
     *           "data" => [
     *              ['id'=>1,'name'=>'qwe'],
     *              ['id'=>1,'name'=>'qwe'],
     *         ],
     *         [
     *           "secondLevelName" => "听力判断题",
     *           "data" => [
     *              ['id'=>1,'name'=>'qwe'],
     *              ['id'=>1,'name'=>'qwe'],
     *         ],
     *    ],
     * ]
     *
     * @param array $array
     * @param array $mappings
     *
     * @internal param $data
     * @return array
     */
    public static function keyToField(array $array, array $mappings)
    {
        //如果没有映射表，直接返回
        if (empty($mappings)) {
            return $array;
        }

        //本次结果集合
        $result = [];
        //取出第 i 次递归使用的映射键值，比如firstLevelName,作为本次 key 映射的值
        $field = array_shift($mappings);
        foreach ($array as $key => $value) {
            //如果数组的key不是字符串,那么就不使用映射替换，这表示这个数组可能是 php 自带的数字键。我们不替换这种情况
//            if (!is_string($key)) {
//                return $value;
//            }

            //如果数组的 value 还是一个数组,递归调用,处理这个子集
            if (is_array($value)) {
                $value = self::keyToField($value, $mappings);
            }
            //将原来数组的字符串 key 变成数组的值。
            $result [] = [
                $field => $key,
                'list' => array_values($value),
            ];
        }

        return $result;
    }

    /**
     * ---------------------------------------------------------
     * 判断多维数组中某一个元素是否有某个 key
     * --------------------------------------------------------
     * 由于isset函数在判断是否存在的时候， 会把null标记为不存在
     * 所以这里使用array_key_exists来查询是否存在
     * ------------------------------------------
     *
     * @param array $array 要判断的多维数组
     * @param string|int $key 某一个元素
     *
     * @return bool
     */
    public static function arrayKeyExists(array $array, $key)
    {
        //如果数组为空，直接返回 false
        if (empty($array)) {
            return false;
        }

        //判断第一个元素有没有这个 key 即可
        return array_key_exists($key, current($array));
    }

    /**
     * -----------------------------------------------------------------
     * 按照给定的顺序，对原始数组进行排序
     * -----------------------------------------------------------------
     * 1.支持原始数组中有重复的 key，比如多个元素都有一个 key 叫做"听说"之类的
     * 2.所有不在排序数组中的元素，都会被丢弃掉
     * -----------------------------------------------------------------
     * 1)排序多维数组:
     * 原始数组:
     * $originalArray = [
     *   [ 'name'=>'张三' ,'age'=>20],
     *   [ 'name'=>'李四' ,'age'=>22],
     *   [ 'name'=>'王五' ,'age'=>25],
     * ];
     * 排序数组：
     *   $sortArray = [ '张三','王五','李四'];
     * 指定字段:
     *   $sortKey = 'name';
     * 结果数组：
     *  $result = ArrayHelper::reorderByArray($originalArray,$sortArray,$sortKey);
     *   [
     *   [ 'name'=>'张三' ,'age'=>20],
     *   [ 'name'=>'王五' ,'age'=>25],
     *   [ 'name'=>'李四' ,'age'=>22],
     * ];
     *
     * -------------------------------------
     * 2)排序一维数组
     * 原始数组:
     *  $originalArray = [ 'a','v','w','q'];
     *  $sortArray  = ['a','q','v'];
     * 调用:
     *  $result = ArrayHelper::reorderByArray($originalArray,$sortArray);
     *  结果:
     *      ['a','q','v']
     *
     *
     *
     * ------------------------------------------------------------
     * @param array $originArray 要排序的原始数组
     * @param array $sortArray 要变成的顺序
     * @param string $field 指定的原始数组中的 key
     * @param bool $withUnRelated 是否需要把不在排序数组中过的元素加上去,如果要添加，那么会加到最后边
     *
     * @return array|bool
     */
    public static function reorderByArray(array $originArray, array $sortArray, $field = null, $withUnRelated = false)
    {
        if (empty($sortArray) || empty($originArray)) {
            return $originArray;
        }

        $result = [];
        //遍历给定排序数组，比如给定的值是A,B,C，我们先遍历A,B,C,然后在原始数组中依次查找A,B,C对应的元素，然后添加到结果集
        foreach ($sortArray as $sortKey) {
            //在原始数组中找到这个排序字段的所有元素，顺序插入 result
            foreach ($originArray as $index => $originItem) {
                //获取指定的元素的值，如果没有传 field 参数，就获取当前数组元素,这个主要是用于
                //当我们遍历一维数组的时候
                $originValue = is_null($field) ? $originItem : $originItem[$field];

                if ($originValue == $sortKey) {
                    if ($withUnRelated) {
                        unset($originArray[$index]);
                    }
                    $result [] = $originItem;
                }
            }
        }

        return $withUnRelated ? array_merge($result, $originArray) : $result;
    }

    /**
     * ------------------------------------------------------------------------
     * 数组去重，然后重新建立索引
     * ------------------------------------------------------------------------
     * 因为很多时候数组去重之后索引会断掉，这个时候 json_encode的时候就会从一个普通数组变成键值对数组。
     * 比如数组:
     *  $array = [ 0=> 'xxx', 1=> 'xxx', 2=>'xxx'] ;
     *  $result = json_encode($array);
     *  $result = "["xxx","xxx","xxx"]";
     *
     *  但是如果数组的键是不连续数字，比如：
     *  $array = [ 0=>'xxx', 10=>'xxx', 100=>'xxx'];
     *  $result = json_encode($array);
     *  $result = "{"0":"xxx","10":"xxx","100":"xxx"}";
     * -----------------------------------------------------------------------
     *
     * $originalArray = [ '张三','李四','王五','李四','李四'];
     *
     * $result = ArrayHelper::unique($originalArray);
     *
     * //结果是: ['张三'，'李四'，'王五']
     *
     * @param array $array
     * @param null|int $sortFlag
     *
     * @return array
     */
    public static function unique($array, $sortFlag = null)
    {
        return array_values(array_unique($array, $sortFlag));
    }

    /**
     * -------------------------------------------------------------------------
     * 获取多维数组中的某一列，并且去重
     * -------------------------------------------------------------------------
     * 相当于:
     * array_unique(array_column($originalArray,'key'))
     * -----------------------------------------------------------------------
     * $originalArray = [
     *  ['id'=>1,'name'=>'张三','age'=>25],
     *  ['id'=>2,'name'=>'李四','age'=>25],
     *  ['id'=>2,'name'=>'网路','age'=>25],
     *  ['id'=>3,'name'=>'张三','age'=>25],
     * ];
     *
     * $result = ArrayHelper::uniqueColumn($originalArray,'id');
     *
     * $result = [
     *   '张三','李四'，'网路'
     * ];
     *
     * @param array $array 原始数组
     * @param int|string $field 数组的某一列的 key
     * @param null|int $sortFlag 排序标志位
     *
     * @return array
     */
    public static function uniqueColumn(array $array, $field, $sortFlag = null)
    {
        return self::unique(array_column($array, $field), $sortFlag);
    }

    /**
     * -----------------------------------------------------------------------
     * 根据某一个条件查询数组的数量
     * -----------------------------------------------------------------------
     * 比如:
     * $originalArray = [
     *   ['isRight'=> 'Y', 'age' => '123'] ,
     *   ['isRight'=> 'Y', 'age' => '123'] ,
     *   ['isRight'=> 'N', 'age' => '123'] ,
     * ];
     * $condition = ['isRight' => 'Y'] ;
     *
     * $result = ArrayHelper::countByCondition($originalArray,$condition);  //result => 2
     *
     *
     * @param array $array 原始数组
     * @param array $condition 搜索条件
     *
     * @return int
     */
    public static function countByCondition(array $array, $condition): int
    {
        if (empty($condition)) {
            return count($array);
        }
        $key = key($condition);
        $value = current($condition);

        return count(array_filter($array, function ($arrayElement) use ($key, $value) {
            if (! isset($arrayElement[$key])) {
                return false;
            }

            return $arrayElement[$key] == $value;
        }));
    }

    /**
     * 把数组的 key 变成小写字母开头
     *
     * @param $list
     *
     * @return array
     */
    public static function arrayChangeKeyLcFirst($list)
    {
        if (! is_array($list)) {
            return $list;
        }
        $result = [];
        foreach ($list as $key => $value) {
            //对数字下标任然返回数字
            $result[lcfirst($key)] = $value;
        }

        return $result;
    }

    /**
     * 从数组中随机去一个元素
     * -----------------------------------------------------------------------
     *
     * @param array $data
     *
     * @return mixed|null
     * @throws \Exception
     */
    public static function getRandomOne(array $data)
    {
        //随机去一个索引
        $random = random_int(0, count($data) - 1);

        //要用一个额外的字段去记录遍历的数量，因为这个数组可能 key 不是数字，而是关联数组，
        //这个时候只能用数字去
        $index = 0;
        foreach ($data as $item) {
            if ($index == $random) {
                return $item;
            }
            $index++;
        }

        return null;
    }


    /**
     * -----------------------------------------------------------------------
     * 判断多个 key 在数组中是否存在
     * -----------------------------------------------------------------------
     * 只用来判断一维数组
     * $originalArray = [
     *  'cacheKey'    => 'xxx',
     *  'cacheValue'  => 'xxx',
     *  'cacheTime'   => 'xxx',
     * ];
     *
     * $result = ArrayHelper::multipleKeyExists($originalArray,['cacheKey','cacheValue','cacheTime']);  // $result => true
     *
     * $result = ArrayHelper::multipleKeyExists($originalArray,['cacheKey','cacheValue','cacheTime','naisdno']); //$result => false
     *
     * @param array $array
     * @param array $keys
     *
     * @return bool
     */
    public static function multipleKeyExists(array $array, array $keys)
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $array)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 将数组中 null 值转换为空字符串
     * -----------------------------------------------------------------------
     *
     * @param array $array
     *
     * @return array
     */
    public static function transNull2Empty(array $array)
    {
        $result = [];
        foreach ($array as $key => $item) {
            if (is_array($item)) {
                $item = self::transNull2Empty($item);
            } else {
                $item = $item ?? '';
            }
            $result [$key] = $item;
        }

        return $result;
    }

    /**
     * 获取二维数组指定 key 的结果求和
     *
     * @param array $array
     * @param       $key
     *
     * @return float|int
     */
    public static function sum(array $array, $key)
    {
        return array_sum(array_column($array, $key));
    }

    /**
     * 数组指定 key 求平均值
     *
     * @param array $array
     * @param       $key
     *
     * @return float|int
     */
    public static function average(array $array, $key)
    {
        if (count($array) == 0) {
            return 0;
        }

        $average = self::sum($array, $key) / count($array);

        return round($average, 2);
    }

    /**
     * 在数组中随机取一个元素，并且把该元素从数组中移除。
     * -----------------------------------------------------------------------
     * 注意该方法是引用传参的!!
     *
     * @param array $poolData
     *
     * @return mixed
     * @throws \Exception
     */
    public static function getRandomOneWithRemove(array &$poolData)
    {
        //随机取索引，在 0 ~ (length -1)
        $randomIndex = random_int(0, count($poolData) - 1);
        $random = $poolData[$randomIndex];
        unset($poolData[$randomIndex]);
        //unset 之后必须重置数组索引，不然会导致数组索引的不连续
        //比如原始数组：
        // $arr = [ 'a','b','c','d'];
        // 如果取出来了'b',之后把 b unset 掉，那么之后数组会变成：
        // $arr= [ 0=> 'a', 2=>'c',3=>'d'];
        // 这样下次我们再根据整个数组长度进行随机的时候，会在[0,1,2]之间随机，这个时候其实 '1'这个 index 已经被 unset 掉了，而且最后的元素再也不会被随机。
        $poolData = array_values($poolData);

        return $random;
    }

    /**
     * 把大数组分组，然后根据某一个 key，取出来前几名
     * $arr = [
     *    ['grade'=> 1, 'score'=> 10,],
     *    ['grade'=> 1, 'score'=> 20,],
     *    ['grade'=> 2, 'score'=> 40,],
     *    ['grade'=> 2, 'score'=> 60,],
     *    ['grade'=> 3, 'score'=> 70,],
     *    ['grade'=> 3, 'score'=> 80,],
     * ];
     *
     * $result = ArrayHelper::getTopN($arr,'grade','score',1);
     * $result = [
     *   1 => [
     *    ['grade'=> 1, 'score'=> 20,],
     *   ],
     *   2 => [
     *    ['grade'=> 2, 'score'=> 60,],
     *   ],
     *   3 => [
     *    ['grade'=> 3, 'score'=> 80,],
     *   ],
     * ];
     *
     * @param array $originalArray 原始数组
     * @param string $groupKey 用来分组的 key
     * @param string $sortKey 用来排序的 key
     * @param int $topN 每个分组要获取的数目
     *
     * @return array
     */
    public static function getTopN($originalArray, $groupKey, $sortKey, $topN = 1)
    {
        //按照指定 key 进行分组
        $originalArray = self::index($originalArray, null, $groupKey);

        foreach ($originalArray as &$groupedItems) {
            //把这个分组重新按照指定的 key 进行排序
            self::multisort($groupedItems, $sortKey, SORT_DESC);

            //为了避免如果获取了 top1还得整理数组太麻烦，如果只获取了 top1，那么就不返回数组了。
            if ($topN == 1) {
                $groupedItems = current($groupedItems);
            } else {
                $groupedItems = array_slice($groupedItems, 0, $topN);
            }
        }

        return $originalArray;
    }

    /**
     * 用一个旧的列复制一个新的列。
     *
     * @param array $originalArray
     * @param array $copyColumns
     *
     * @return array
     */
    public static function copyField(array $originalArray, array $copyColumns)
    {
        $fromKey = key($copyColumns);
        $toKey = current($copyColumns);
        foreach ($originalArray as &$item) {
            $item[$toKey] = $item[$fromKey];
        }

        return $originalArray;
    }


    /**
     * 合并多个分组的元素
     * $originalArray =
     * [
     * 'FirstGroup' => [
     * [
     * 'questionId' => '3129134'
     * 'courseSectionId' => '50018288'
     * ],
     * [
     * 'questionId' => '3129134'
     * 'courseSectionId' => '50018288'
     * ],
     * ],
     * 'SecondGroup' => [
     * [
     * 'questionId' => '3129134'
     * 'courseSectionId' => '50018288'
     * ],
     * [
     * 'questionId' => '3129134'
     * 'courseSectionId' => '50018288'
     * ],
     * ],
     *
     * $result = ArrayHelper::mergeGroups($originalArray)
     * $result = [
     * [
     * 'questionId' => '3129134'
     * 'courseSectionId' => '50018288'
     * ],
     * [
     * 'questionId' => '3129134'
     * 'courseSectionId' => '50018288'
     * ],
     * [
     * 'questionId' => '3129134'
     * 'courseSectionId' => '50018288'
     * ],
     * ];
     *
     * @param array $originalArray
     *
     * @return array
     */
    public static function mergeGroups(array $originalArray)
    {
        $result = [];
        foreach ($originalArray as $groupedItems) {
            foreach ($groupedItems as $item) {
                array_push($result, $item);
            }
        }

        return $result;
    }

    /**
     * 根据条件查询某一列
     *
     * @param array $originalArray
     * @param         $key
     * @param Closure $callback
     */
    public static function getColumnByCondition(array $originalArray, $key, closure $callback)
    {
    }

    /**
     * 在一维数组中去除某些值
     * -----------------------------------------------------------------------
     * $result = ArrayHelper::forget(range(1,10),[1,2,3,],[4,5,6,7]);
     * $result = [8,9,10];
     *
     * @param array $originalValues 原始的一维数组
     * @param array ...$forgetValuesArray 可以传递多个要去重的数组
     *
     * @return array
     */
    public static function forget(array $originalValues, array ... $forgetValuesArray)
    {
        $totalForgetValues = array_merge_recursive(...$forgetValuesArray);

        return self::filterArray($originalValues, function ($value) use ($totalForgetValues) {
            return ! in_array($value, $totalForgetValues);
        });
    }

    /**
     * 带重新索引数组的 filter
     *
     * @param array $original
     * @param null $closure
     *
     * @return array
     */
    public static function filterArray(array $original, $closure = null)
    {
        if ($closure) {
            return array_values(array_filter($original, $closure));
        }

        return array_values(array_filter($original));
    }

    /**
     * 获取数组中的最大元素。
     * ------------------------------------------------------------------------------------------------------------
     * 支持获取一维数组和对象（二维）数组, 注意无论是一维数组、二维数组，返回的永远是最大的那个值，而不是对象.
     *
     * 1.一维数组
     * $arr = range(1,100);
     * $result = ArrayHelper::max($arr);  // $result = 100;
     *
     * 2. 二维数组（对象数组)
     * $arr = [
     *   [ 'age' => 19, 'name' => 'sui'],
     *   [ 'age' => 25, 'name' => 'ting'],
     *   [ 'age' => 40, 'name' => 'wei'],
     * ];
     *
     * $result = ArrayHelper::max($arr,'age');  // $result = 40;
     *
     * @param array $originalArray
     * @param null|string $key 指定的某一列，如果是一维数组这个 key 可以不传； 如果是二维数组，那么就获取这个 key 最大的值。
     *
     * @return mixed|null
     */
    public static function max(array $originalArray, $key = null)
    {
        if (is_null($key)) {
            return max($originalArray);
        }

        if (isset($originalArray[$key])) {
            return null;
        }

        return max(array_column($originalArray, $key));
    }

    /**
     * 根据指定字段进行分组-一维数组，不存在重复key情况
     * @param $array
     * @param $specifyValue
     * @return array
     */
    public static function groupArrayBySpecifyValue(array $array, $specifyValue)
    {
        $result = [];
        foreach ($array as $item) {
            $result[$item[$specifyValue]] = $item;
        }

        return $result;
    }

    /**
     * 根据指定字段进行分组-多维数组
     * @param $array
     * @param $specifyValue
     * @return array
     */
    public static function groupMultipleArrayBySpecifyValue(array $array, $specifyValue)
    {
        $result = [];
        foreach ($array as $item) {
            $result[$item[$specifyValue]][] = $item;
        }

        return $result;
    }
}
