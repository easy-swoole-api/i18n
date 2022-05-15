<?php

namespace EasyApi\I18N;

use EasyApi\I18N\Consts\Core;
use EasySwoole\Component\Context\ContextManager;
use EasySwoole\Component\Singleton;
use EasySwoole\Http\Response;
use EasySwoole\Utility\File;

class Lang
{
    use Singleton;

    /**
     * 多语言信息
     * @var array
     */
    private $lang = [];

    /**
     * 当前语言
     * @var string
     */
    private $range = 'zh-cn';

    /**
     * 多语言自动侦测变量名
     * @var string
     */
    protected $langDetectVar = 'lang';

    /**
     * 多语言cookie变量
     * @var string
     */
    protected $langCookieVar = 'lava_var';

    /**
     * 允许的多语言列表
     * @var array
     */
    protected $allowLangList = [];

    /**
     * Accept-Language转义为对应语言包名称 系统默认配置
     * @var string
     */
    protected $acceptLanguage = [
        'zh-hans-cn' => 'zh-cn',
    ];

    public function __construct()
    {
        // 加载内置语言包
        $this->load(__DIR__ . '/zh-cn.php', 'zh-cn');
    }

    // 设定当前的语言
    public function range($range = '')
    {
        if ('' == $range) {
            return $this->range;
        } else {
            $this->range = $range;
        }
    }

    /**
     * 设置语言定义(不区分大小写)
     * @access public
     * @param  string|array  $name 语言变量
     * @param  string        $value 语言值
     * @param  string        $range 语言作用域
     * @return mixed
     */
    public function set($name, $value = null, $range = '')
    {
        $range = $range ?: $this->range;
        // 批量定义
        if (!isset($this->lang[$range])) {
            $this->lang[$range] = [];
        }

        if (is_array($name)) {
            return $this->lang[$range] = array_change_key_case($name) + $this->lang[$range];
        }

        return $this->lang[$range][strtolower($name)] = $value;
    }

    /**
     * 加载语言定义(不区分大小写)
     * @access public
     * @param  string|array  $file   语言文件
     * @param  string        $range  语言作用域
     * @return array
     */
    public function load($file, $range = '')
    {
        $range = $range ?: $this->range;
        if (!isset($this->lang[$range])) {
            $this->lang[$range] = [];
        }

        // 批量定义
        if (is_string($file)) {
            $file = [$file];
        }

        $lang = [];

        foreach ($file as $_file) {
            if (is_file($_file)) {
                $_lang = include_once $_file;
                if (is_array($_lang)) {
                    $lang = array_change_key_case($_lang) + $lang;
                }
            }
        }

        if (!empty($lang)) {
            $this->lang[$range] = $lang + $this->lang[$range];
        }

        return $this->lang[$range];
    }

    /**
     * 加载语言定义(不区分大小写)
     * @param string $dir
     */
    public function loads(string $dir)
    {
        $files = File::scanDirectory($dir);
        foreach ($files['files'] as $file) {
            $fileInfo = pathinfo($file);
            $this->load($file, $fileInfo['filename']);
        }
    }

    /**
     * 获取语言定义(不区分大小写)
     * @access public
     * @param  string|null   $name 语言变量
     * @param  string        $range 语言作用域
     * @return bool
     */
    public function has($name, $range = '')
    {
        $range = $range ?: $this->range;

        return isset($this->lang[$range][strtolower($name)]);
    }

    /**
     * 获取语言定义(不区分大小写)
     * @access public
     * @param  string|null   $name 语言变量
     * @param  array         $vars 变量替换
     * @param  string        $range 语言作用域
     * @return mixed
     */
    public function get($name = null, $vars = [], $range = '')
    {
        $range = $range ?: $this->range;

        // 空参数返回所有定义
        if (is_null($name)) {
            return $this->lang[$range];
        }

        $key   = strtolower($name);
        $value = isset($this->lang[$range][$key]) ? $this->lang[$range][$key] : $name;

        // 变量解析
        if (!empty($vars) && is_array($vars)) {
            /**
             * Notes:
             * 为了检测的方便，数字索引的判断仅仅是参数数组的第一个元素的key为数字0
             * 数字索引采用的是系统的 sprintf 函数替换，用法请参考 sprintf 函数
             */
            if (key($vars) === 0) {
                // 数字索引解析
                array_unshift($vars, $value);
                $value = call_user_func_array('sprintf', $vars);
            } else {
                // 关联索引解析
                $replace = array_keys($vars);
                foreach ($replace as &$v) {
                    $v = "{:{$v}}";
                }
                $value = str_replace($replace, $vars, $value);
            }
        }

        return $value;
    }

    /**
     * 自动侦测设置获取语言选择
     * @access public
     * @return string
     */
    public function detect()
    {
        // 自动侦测设置获取语言选择
        $langSet = '';

        /** @var \EasySwoole\Http\Request $esRequest */
        $esRequest = ContextManager::getInstance()->get(Core::HTTP_REQUEST);
        $swooleRequest = $esRequest->getSwooleRequest();

        $get = $swooleRequest->get;
        $server = $swooleRequest->server;
        $cookie = $swooleRequest->cookie;
        $httpAcceptLanguage = strtolower('HTTP_ACCEPT_LANGUAGE');

        if (isset($get[$this->langDetectVar])) {
            // url中设置了语言变量
            $langSet = strtolower($get[$this->langDetectVar]);
        } elseif (isset($cookie[$this->langCookieVar])) {
            // Cookie中设置了语言变量
            $langSet = strtolower($cookie[$this->langCookieVar]);
        } elseif (isset($server[$httpAcceptLanguage])) {
            // 自动侦测浏览器语言
            preg_match('/^([a-z\d\-]+)/i', $server[$httpAcceptLanguage], $matches);
            $langSet = strtolower($matches[1]);
            if (isset($this->acceptLanguage[$langSet])) {
                $langSet = $this->acceptLanguage[$langSet];
            }
        }

        if (empty($this->allowLangList) || in_array($langSet, $this->allowLangList)) {
            // 合法的语言
            $this->range = $langSet ?: $this->range;
        }

        return $this->range;
    }

    /**
     * 设置当前语言到Cookie
     * @access public
     * @param  string $lang 语言
     * @return void
     */
    public function saveToCookie($lang = null)
    {
        $range = $lang ?: $this->range;

        /** @var Response $esResponse */
        $esResponse = ContextManager::getInstance()->get(Core::HTTP_RESPONSE);

        $esResponse->withAddedCookie([$this->langCookieVar => $range]);
    }

    /**
     * 设置语言自动侦测的变量
     * @access public
     * @param  string $var 变量名称
     * @return void
     */
    public function setLangDetectVar($var)
    {
        $this->langDetectVar = $var;
    }

    /**
     * 设置语言的cookie保存变量
     * @access public
     * @param  string $var 变量名称
     * @return void
     */
    public function setLangCookieVar($var)
    {
        $this->langCookieVar = $var;
    }

    /**
     * 设置允许的语言列表
     * @access public
     * @param  array $list 语言列表
     * @return void
     */
    public function setAllowLangList(array $list)
    {
        $this->allowLangList = $list;
    }

    /**
     * 设置转义的语言列表
     * @access public
     * @param  array $list 语言列表
     * @return void
     */
    public function setAcceptLanguage(array $list)
    {
        $this->acceptLanguage = array_merge($this->acceptLanguage, $list);
    }
}