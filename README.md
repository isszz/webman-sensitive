# webman-sensitive
Webman 敏感词检测，过滤，标记

<p>
    <a href="https://packagist.org/packages/isszz/webman-sensitive"><img src="https://img.shields.io/badge/php->=8.0-8892BF.svg" alt="Minimum PHP Version"></a>
    <a href="https://packagist.org/packages/isszz/webman-sensitive"><img src="https://img.shields.io/badge/webman->=1.4.x-8892BF.svg" alt="Minimum Webman Version"></a>
    <a href="https://packagist.org/packages/isszz/webman-sensitive"><img src="https://poser.pugx.org/isszz/webman-sensitive/v/stable" alt="Stable Version"></a>
    <a href="https://packagist.org/packages/isszz/webman-sensitive"><img src="https://poser.pugx.org/isszz/webman-sensitive/downloads" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/isszz/webman-sensitive"><img src="https://poser.pugx.org/isszz/webman-sensitive/license" alt="License"></a>
</p>

## 安装

```shell
composer require isszz/webman-sensitive
```

## 配置
```php
return [
    'enable'  => true,

    // 支持file，array，也可以指向自己敏感词库文件路径
    // file模式时，敏感词库位于webman根目录的config/plugin/isszz/webman-sensitive/SensitiveWord.txt，也可以指向自定义的词库文件路径
    'mode' => 'file',

    'config' => [
        'repeat' => true, // 重复替换为敏感词相同长度的字符
        'replace_char' => '*', // 替换字符
        // 标记敏感词，标签生成<mark>敏感词</mark>
        'mark' => 'mark', 
    ],

    // 干扰因子
    'interference_factors' => [
        ' ', '&', '*', '/', '|', '@', '.', '^', '~', '$',
    ],

    // 数组模式敏感词
    'sensitive_words' => [
        '工口',
        '里番',
        '性感美女',
    ]
];

```

## 使用

facade方式
```php
use isszz\sensitive\facade\Sensitive;

class Index
{
    public function add()
    {
        // 设置干扰因子
        Sensitive::interferenceFactor(['(', ')', ',', '，', ';', '；', '。']);

        // 添加一个额外的敏感词，words参数支持单敏感词，多词也可以用|分割，或者直接传入多个敏感词数组
        // words = 性感美女|分隔符
        // words = ['性感美女', '数组']
        Sensitive::add(words: '性感美女');

        // 删除的敏感词，words参数同添加的格式一样
        // 第二个参数once为true时，只针对当次: is，replace，mark，操作生效
        Sensitive::remove(words: '性感美女', once: true);

        // 检测
        if (Sensitive::is(content: '检测语句')) {
            return json(['code' => 1, 'msg' => '输入内容包含敏感词，请注意用词。']);
        }

        // 替换
        $replaced = Sensitive::add(words: '垃圾')->replace(content: '替换语句垃圾要被替换', replaceChar: '*', repeat: false);

        // 标记敏感词
        $marked = Sensitive::add(words: '尼玛')->mark(content: '标记的内容，这里尼玛要被标记', tag: 'bad');

        // 提取内容中的所有敏感词
        $badWords = Sensitive::add('狗逼')->get('提取内容中的所有敏感词，狗逼，还有SB都会被提取');

        // 自定义敏感词库
        // 文件方式
        Sensitive::custom('/config/SensitiveWord.txt')
            ->is('检测尼玛的语句');

        // 数组方式
        Sensitive::custom([
            '垃圾', '尼玛', 
            //...
        ])->is('检测尼玛的语句');

        // 文件词库模式，可以添加新敏感词到词库文件
        // data参数可以是一个数组也可以是用|分割敏感词的字符串
        // append参数为true是追加模式，false时先提取词库，再去重，然后合并写入
        $sensitive->addWordToFile(data: '狗逼|傻缺', append: false);
    }
}

```
依赖注入方式
```php
use isszz\sensitive\Sensitive;

class Index
{
    public function add(Sensitive $sensitive)
    {
        // 设置干扰因子
        $sensitive->interferenceFactor(['(', ')', ',', '，', ';', '；', '。']);
        // ...
    }
}
```
助手函数方式
```php
class Index
{
    public function add(Sensitive $sensitive)
    {
        // 设置干扰因子，后返回的Sensitive实例可使用：is，replace，mark
        sensitive_interference_factor(['(', ')', ',', '，', ';', '；', '。'])
            ->is('检测语句尼玛');

        // 添加敏感词，后返回的Sensitive实例可使用：is，replace，mark
        sensitive_add(words: '性感美女')
            ->mark('你是一个性感美女，你说是不是？');

        // 移除敏感词，后返回的Sensitive实例可使用：is，replace，mark
        // 第二个参数once为true时，只针对当次: is，replace，mark，操作生效
        sensitive_remove(words: '工口', once: true)
            ->mark('你这个SB是不是想看工口类的动漫？哈哈！');

        // 检测敏感词
        if (sensitive_is('检测语句尼玛')) {
            return json(['code' => 1, 'msg' => '输入内容包含敏感词，请注意用词。']);
        }

        // replaceChar是用来设置要被替换的敏感词
        // repeat为true时根据检测出的敏感词长度设置replaceChar
        $replaced = sensitive_replace(content: '替换语句垃圾要被替换', replaceChar: '*', repeat: true);
        // tag参数是用来设置包裹敏感词的标签名例如: 这里<bad>SB</bad>要被标记
        $marked = sensitive_mark(content: '标记的内容，这里SB要被标记', tag: 'bad');

        // 提取内容中的所有敏感词
        $badWords = sensitive_get('谁是SB，谁是狗逼，谁是傻缺');

        // 自定义敏感词库
        // 文件方式
        sensitive_custom('/config/SensitiveWord.txt')
            ->is('检测尼玛的语句');

        // 数组方式
        sensitive_custom([
            '垃圾', '尼玛', 
            //...
        ])->is('检测尼玛的语句');

        // 文件词库模式，可以添加新敏感词到词库文件
        // data参数可以是一个数组也可以是用|分割敏感词的字符串
        // append参数为true是追加模式，false时先提取词库，再去重，然后合并写入
        sensitive_add_word_to_file(data: '狗逼|傻缺', append: false);

    }
}
```