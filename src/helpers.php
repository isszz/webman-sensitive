<?php
declare(strict_types=1);

use isszz\sensitive\facade\Sensitive;

if (!function_exists('sensitive_is')) {
    /**
     * 被检测内容是否合法
     * 
     * @param string $content
     *
     * @return bool
     * @throws \isszz\sensitive\SensitiveException
     */
    function sensitive_is(string $content)
    {
        return Sensitive::is($content);
    }
}

if (!function_exists('sensitive_replace')) {
    /**
     * 替换敏感字字符
     *
     * @param string $content 文本内容
     * @param string $replaceChar 替换字符
     * @param bool $repeat 重复替换为敏感词相同长度的字符
     *
     * @return mixed
     * @throws \isszz\sensitive\SensitiveException
     */
    function sensitive_replace(string $content, string $replaceChar = '', bool $repeat = false)
    {
        return Sensitive::replace($content, $replaceChar, $repeat);
    }
}

if (!function_exists('sensitive_mark')) {
    /**
     * 标记敏感词
     *
     * @param string $content 文本内容
     * @param string $tag 标签开头，如mark
     *
     * @return mixed
     * @throws \isszz\sensitive\SensitiveException
     */
    function sensitive_mark(string $content, string $tag = '')
    {
        return Sensitive::mark($content, $tag);
    }
}

if (!function_exists('sensitive_get')) {
    /**
     * 检测提取文字中的敏感词
     *
     * @param string $content 待检测内容
     * @param int $wordNum 需要获取的敏感词数量，默认获取全部
     * @param int $matchType 匹配类型，默认为最小匹配规则
     * 
     * @return array
     * @throws \isszz\sensitive\SensitiveException
     */
    function sensitive_get(string $content, $wordNum = 0, $matchType = 1)
    {
        return Sensitive::get($content, $wordNum, $matchType);
    }
}

if (!function_exists('sensitive_custom')) {
    /**
     * 自定义构建敏感词树，文件方式|数组方式
     *
     * @param string|array $custom
     *
     * @return \isszz\sensitive\Sensitive
     * @throws \isszz\sensitive\SensitiveException
     */
    function sensitive_custom(string $content, string $tag = '')
    {
        return Sensitive::custom($content, $tag);
    }
}

if (!function_exists('sensitive_add')) {
    /**
     * 添加额外的敏感词
     * 
     * @param string|array $words
     * 
     * @return \isszz\sensitive\Sensitive
     */ 
    function sensitive_add(string|array $words)
    {
        return Sensitive::add($words);
    }
}

if (!function_exists('sensitive_remove')) {
    /**
     * 删除敏感词
     * 
     * @param string|array $words
     * @param bool $once
     * 
     * @return \isszz\sensitive\Sensitive
     */ 
    function sensitive_remove(string|array $words, bool $once = false)
    {
        return Sensitive::remove($words);
    }
}

if (!function_exists('sensitive_interference_factor')) {
    /**
     * 添加干扰因子
     * 
     * @param array $interferenceFactors
     * 
     * @return \isszz\sensitive\Sensitive
     */
    function sensitive_interference_factor(array $interferenceFactors)
    {
        return Sensitive::interferenceFactor($interferenceFactors);
    }
}

if (!function_exists('sensitive_add_word_to_file')) {
    /**
     * 向敏感词库文件添加新词
     *
     * @param string|array $data 添加的新敏感词
     * @param bool $append 是否追加模式，false时会提取后合并去掉重复再写入
     * 
     * @return string
     */
    function sensitive_add_word_to_file(string|array $data, bool $append = true)
    {
        return Sensitive::addWordToFile($data, $append);
    }
}

if (!function_exists('sensitive_mb_strlen')) {
    /**
     * @param string $str
     * @param string|null $encoding
     *
     * @return int
     * @throws \isszz\sensitive\SensitiveException
     */
    function sensitive_mb_strlen(string $str, string|null $encoding = null)
    {
        $length = mb_strlen($str, $encoding);

        if ($length === false) {
            throw new SensitiveException('Invalid encoding');
        }

        return $length;
    }
}
