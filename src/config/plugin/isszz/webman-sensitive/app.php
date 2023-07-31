<?php

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
