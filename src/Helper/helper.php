<?php
/**
 * Created by PhpStorm.
 * User: XueSi <1592328848@qq.com>
 * Date: 2022/5/15
 * Time: 7:22 下午
 */
declare(strict_types=1);

if (!function_exists('lang')) {
    /**
     * 获取语言变量值
     *
     * @param string $name 语言变量名
     * @param array  $vars 动态变量值
     * @param string $lang 语言
     *
     * @return mixed
     */
    function lang($name, $vars = [], $lang = '')
    {
        return \EasyApi\I18N\Lang::getInstance()->get($name, $vars, $lang);
    }
}