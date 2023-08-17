<?php
declare(strict_types=1);

namespace isszz\sensitive;

class Sensitive
{
	/**
	 * 配置
	 * 
	 * @var array
	 */
	protected array $config;

	/**
	 * 待检测语句长度
	 *
	 * @var int
	 */
	protected int $contentLength = 0;

	/**
	 * 敏感词库树
	 *
	 * @var \isszz\sensitive\HashMap|null
	 */
	protected \isszz\sensitive\HashMap|null $wordTree = null;

	/**
	 * 存放待检测语句敏感词
	 *
	 * @var array|null
	 */
	protected array|null $badWordList = null;

	/**
	 * 干扰因子集合
	 * 
	 * @var array
	 */
	protected array $interferenceFactors = [];

	/**
	 * 删除的敏感词列表
	 * 
	 * @var array
	 */
	public array $removeList = [];

	/**
	 * Sensitive constructor
	 */
	public function __construct()
	{
		$config = config('plugin.isszz.webman-sensitive.app', []);

		if (!$config) {
			throw new SensitiveException('The configuration cannot be empty', 1);
		}

		$mode = $config['mode'] ?? 'file';

		$this->config = $config['config'];

		// 配置中的干扰因子
		$this->interferenceFactors = $config['interference_factors'] ?? [];

		if ($mode == 'array') {
			$this->setTree($config['sensitive_words'] ?? []);
		} else {
			$this->setFile(
				is_file($mode) ? $mode : config_path('plugin') . DIRECTORY_SEPARATOR .'isszz'. DIRECTORY_SEPARATOR .'webman-sensitive'. DIRECTORY_SEPARATOR .'SensitiveWord.txt'
			);
		}
	}

	/**
	 * 被检测内容是否合法|简写
	 *
	 * @param $content
	 *
	 * @return bool
	 * @throws \isszz\sensitive\SensitiveException
	 */
	public function is(string $content)
	{
		return $this->check($content);
	}

	/**
	 * 被检测内容是否合法
	 *
	 * @param $content
	 *
	 * @return bool
	 * @throws \isszz\sensitive\SensitiveException
	 */
	public function check(string $content)
	{
		$this->contentLength = sensitive_mb_strlen($content, 'utf-8');

		for ($length = 0; $length < $this->contentLength; $length++) {
			$matchFlag = 0;
			$tempMap = $this->wordTree;
			for ($i = $length; $i < $this->contentLength; $i++) {
				$keyChar = mb_substr($content, $i, 1, 'utf-8');

				// 检测干扰因子
				if ($this->checkInterferenceFactor($keyChar)) {
					$matchFlag++;
					continue;
				}

				// 获取指定节点树
				$nowMap = $tempMap->get($keyChar);

				// 不存在节点树，直接返回
				if (empty($nowMap)) {
					break;
				}

				// 找到相应key，偏移量+1
				$tempMap = $nowMap;
				$matchFlag++;

				// 如果为最后一个匹配规则,结束循环，返回匹配标识数
				if ($nowMap->get('isEnd') === false) {
					continue;
				}

				return true;
			}

			// 找到相应key
			if ($matchFlag <= 0) {
				continue;
			}

			// 需匹配内容标志位往后移
			$length += $matchFlag - 1;
		}

		$this->recoverRemove();
		return false;
	}

	/**
	 * 替换敏感字字符
	 *
	 * @param string $content 文本内容
	 * @param string $replaceChar 替换字符
	 * @param bool $repeat 重复替换为敏感词相同长度的字符
	 * @param int $matchType 匹配类型，默认为最小匹配规则
	 *
	 * @return mixed
	 * @throws \isszz\sensitive\SensitiveException
	 */
	public function replace(string $content, string $replaceChar = '', bool $repeat = false, $matchType = 1)
	{
		if (empty($content)) {
			throw new SensitiveException('Please fill in the content of the test', 1);
		}

		$replaceChar = $replaceChar ?: $this->config['replace_char'];

		if(!$repeat) {
			$repeat = $this->config['repeat'] ?? false;
		}

		$badWordList = $this->badWordList ? $this->badWordList : $this->get($content, $matchType);

		// 未检测到敏感词，直接返回
		if (empty($badWordList)) {
			return $content;
		}

		foreach ($badWordList as $badWord) {
			$hasReplacedChar = $replaceChar;
			// $badWord = $this->ltrimInterferenceFactorBadWord($badWord);
			if ($repeat) {
				$hasReplacedChar = $this->dfaBadWordConversChars($badWord, $replaceChar);
			}

			$content = str_replace($badWord, $hasReplacedChar, $content);
		}

		return $content;
	}

	/**
	 * 标记敏感词
	 *
	 * @param string $content 文本内容
	 * @param string $tag 标签开头，如mark
	 * @param int $matchType 匹配类型，默认为最小匹配规则
	 *
	 * @return mixed
	 * @throws \isszz\sensitive\SensitiveException
	 */
	public function mark(string $content, string $tag = '', $matchType = 1)
	{
		if (empty($content)) {
			throw new SensitiveException('Please fill in the content of the test', 1);
		}

		if(!$tag) {
			$tag = $this->config['mark'] ?? 'mark';
		}

		$sTag = '<'. $tag .'>';
		$eTag = '</'. $tag .'>';

		$badWordList = $this->badWordList ? $this->badWordList : $this->get($content, $matchType);

		// 未检测到敏感词，直接返回
		if (empty($badWordList)) {
			return $content;
		}

		$badWordList = array_unique($badWordList);

		foreach ($badWordList as $badWord) {
			// $badWord = $this->ltrimInterferenceFactorBadWord($badWord);
			$replaceChar = $sTag . $badWord . $eTag;
			$content = str_replace($badWord, $replaceChar, $content);
		}

		return $content;
	}

	/**
	 * 检测文字中的敏感词
	 *
	 * @param string $content 待检测内容
	 * @param int $matchType 匹配类型，默认为最小匹配规则
	 * @param int $wordNum 需要获取的敏感词数量，默认获取全部
	 * 
	 * @return array
	 * @throws \isszz\sensitive\SensitiveException
	 */
	public function get(string $content, $matchType = 1, $wordNum = 0)
	{
		$this->contentLength = sensitive_mb_strlen($content, 'utf-8');
		$badWordList = [];

		for ($length = 0; $length < $this->contentLength; $length++) {
			$matchFlag = 0;
			$flag = false;
			$tempMap = $this->wordTree;

			for ($i = $length; $i < $this->contentLength; $i++) {
				$keyChar = mb_substr($content, $i, 1, 'utf-8');

				// 检测干扰因子
				if ($this->checkInterferenceFactor($keyChar)) {
					$matchFlag++;
					continue;
				}

				// 获取指定节点树
				$nowMap = $tempMap->get($keyChar);

				// 不存在节点树，直接返回
				if (empty($nowMap)) {
					break;
				}

				// 存在，则判断是否为最后一个
				$tempMap = $nowMap;

				// 找到相应key，偏移量+1
				$matchFlag++;

				// 如果为最后一个匹配规则,结束循环，返回匹配标识数
				if ($nowMap->get('isEnd') === false) {
					continue;
				}

				$flag = true;

				// 最小规则，直接退出
				if ($matchType === 1)  {
					break;
				}
			}

			if (!$flag) {
				$matchFlag = 0;
			}

			if ($matchFlag > 0) {
				$badWordList[] = $this->ltrimInterferenceFactorBadWord(mb_substr($content, $length, $matchFlag, 'utf-8'));

				// 有返回数量限制
				if ($wordNum > 0 && count($badWordList) == $wordNum) {
					return $badWordList;
				}

				// 需匹配内容标志位往后移
				$length += $matchFlag - 1;
			}
		}

		$this->recoverRemove();

		return $badWordList;
	}

	/**
	 * 添加额外的敏感词
	 * 
	 * @param string|array $words
	 * 
	 * @return $this
	 */ 
	public function add(string|array $words)
	{
		if(!$this->wordTree) {
			throw new SensitiveException('Please initialize Sensitive first', 6);
		}

		if (is_string($words) && str_contains($words, '|')) {
			$words = explode('|', $words);
		}

		foreach ((array) $words as $word) {
			$this->buildWordToTree($word);
		}

		return $this;
	}

	/**
	 * 删除敏感词
	 * 
	 * @param string|array $words
	 * @param bool $once
	 * 
	 * @return $this
	 */ 
	public function remove(string|array $words, bool $once = false)
	{
		if(!$this->wordTree) {
			throw new SensitiveException('Please initialize Sensitive first', 6);
		}

		if (is_string($words) && str_contains($words, '|')) {
			$words = explode('|', $words);
		}

		foreach ((array) $words as $word) {
			$this->removeToTree($word, $once);
		}

		return $this;
	}

	/**
	 * 从敏感词树删除
	 * 
	 * @param string|array $words
	 * @param bool $once
	 * 
	 * @return $this
	 */ 
	public function removeToTree(string $word, bool $once = false)
	{
		for ($i = 0; $i < sensitive_mb_strlen($word, 'utf-8'); $i++) {
			$this->wordTree->remove(mb_substr($word, $i, 1, 'utf-8'));
		}

		// 放入待恢复
		$once === true && $this->removeList[] = $word;
	}

	/**
	 * 恢复删除的敏感词
	 * 
	 * @return mixed
	 */
	public function recoverRemove()
	{
		if (!$this->removeList) {
			return false;
		}

		$this->add($this->removeList);
		$this->removeList = [];

		return true;
	}

	/**
	 * 自定义构建敏感词树，文件方式|数组方式
	 *
	 * @param string|array $custom
	 *
	 * @return $this
	 * @throws \isszz\sensitive\SensitiveException
	 */
	public function custom(string|array $custom)
	{
		if (is_string($custom)) {
			$this->setFile($custom);
		}

		if (is_array($custom)) {
			$this->setTree($custom);
		}

		return $this;
	}

	/**
	 * 构建敏感词树，文件方式
	 *
	 * @param string $file
	 *
	 * @return $this
	 * @throws \isszz\sensitive\SensitiveException
	 */
	public function setFile(string $file)
	{
		if (!is_file($file)) {
			throw new SensitiveException('The sensitive words file does not exist', 3);
		}

		$file = $this->getSensitiveWordPath($file);

		$this->wordTree = $this->wordTree ?: new HashMap;

		foreach ($this->yieldToReadFile($file) as $words) {
			$this->buildWordToTree(trim($words));
		}

		return $this;
	}

	/**
	 * 构建敏感词树，数组方式
	 *
	 * @param array|null $sensitiveWords
	 *
	 * @return $this
	 * @throws \isszz\sensitive\SensitiveException
	 */
	public function setTree(array|null $sensitiveWords = null)
	{
		if (empty($sensitiveWords)) {
			throw new SensitiveException('The sensitive words cannot be empty', 2);
		}

		$this->wordTree = $this->wordTree ?: new HashMap;

		foreach ($sensitiveWords as $word) {
			$this->buildWordToTree(trim($word));
		}
		
		return $this;
	}

	/**
	 * 添加干扰因子
	 * 
	 * @param array $interferenceFactors
	 * 
	 * @return $this
	 */
	public function interferenceFactor(array $interferenceFactors)
	{
		$this->interferenceFactors = array_unique(array_merge($this->interferenceFactors, $interferenceFactors));

		return $this;
	}

	/**
	 * 删除敏感词前的干扰因子
	 *
	 * @param string $word 需要处理的敏感词
	 * 
	 * @return string
	 */
	public function ltrimInterferenceFactorBadWord(string $word)
	{
		$characters = '';
		foreach($this->interferenceFactors as $interferenceFactor) {
			$characters .= $interferenceFactor. '\\' .' '. $interferenceFactor;
		}

		return ltrim($word, $characters);
	}

	/**
	 * 向敏感词库文件添加新词
	 *
	 * @param string|array $data 添加的新敏感词
	 * @param bool $append 是否追加模式，false时会提取后合并去掉重复再写入
	 * 
	 * @return string
	 */
	public function addWordToFile(string|array $data, bool $append = true)
	{
		$mode = config('plugin.isszz.webman-sensitive.app.mode', 'file');

		if ($mode == 'array') {
			throw new SensitiveException('Array mode cannot be added', 8);
		}

		$file = is_file($mode) ? $mode : config_path('plugin') . DIRECTORY_SEPARATOR .'isszz'. DIRECTORY_SEPARATOR .'webman-sensitive'. DIRECTORY_SEPARATOR .'SensitiveWord.txt';

		if (!is_file($file)) {
			throw new SensitiveException('Sensitive thesaurus file does not exist', 7);
		}

		$file = $this->getSensitiveWordPath($file);

		if (is_string($data) && str_contains($data, '|')) {
			$data = explode('|', $data);
		}

		$data = array_filter((array) $data);

		// 追加模式
		if ($append === true) {
			$bool = file_put_contents($file, PHP_EOL . implode(PHP_EOL, $data), FILE_APPEND) !== false;
		} else { 
			// 重写模式
			$words = [];
			foreach ($this->yieldToReadFile($file) as $word) {
				$words[] = trim($word);
			}

			$bool = file_put_contents($file, implode(PHP_EOL, array_unique(array_merge($words, $data)))) !== false;
		}

		// phar update file
		if ($bool) {
			$this->getSensitiveWordPath($file, true);
		}

		return $bool;
	}

	/**
	 * 读取敏感词库文件
	 *
	 * @param string $file
	 *
	 * @throws \isszz\sensitive\SensitiveException
	 */
	protected function yieldToReadFile(string $file)
	{
		$handle = fopen($file, 'r');

		if (!$handle) {
			throw new SensitiveException('Read file failed', 4);
		}
		
        while (!feof($handle)) {
            $line = fgets($handle);
            if (!is_string($line)) {
                continue;
            }

            yield str_replace(['\'', ' ', PHP_EOL, ','], '', $line);
        }

		fclose($handle);
	}

	/**
	 * 将单个敏感词构建成树结构
	 */
	protected function buildWordToTree(string $word = '')
	{
		if ($word === '') {
			return;
		}

		$tree = $this->wordTree;

		$wordLength = sensitive_mb_strlen($word, 'utf-8');
		for ($i = 0; $i < $wordLength; $i++) {
			$keyChar = mb_substr($word, $i, 1, 'utf-8');

			// 获取子节点树结构
			$tempTree = $tree->get($keyChar);

			if ($tempTree) {
				$tree = $tempTree;
			} else {
				// 设置标志位
				$newTree = new HashMap;
				$newTree->put('isEnd', false);

				// 添加到集合
				$tree->put($keyChar, $newTree);
				$tree = $newTree;
			}

			// 到达最后一个节点
			if ($i == $wordLength - 1) {
				$tree->put('isEnd', true);
			}
		}

		return;
	}

	/**
	 * 敏感词替换为对应长度的字符
	 * @param $word
	 * @param $char
	 *
	 * @return string
	 * @throws \DfaFilter\Exceptions\PdsSystemException
	 */
	protected function dfaBadWordConversChars($word, $char)
	{
		$str = '';
		$length = sensitive_mb_strlen($word, 'utf-8');

		for ($counter = 0; $counter < $length; ++$counter) {
			$str .= $char;
		}

		return $str;
	}

	/**
	 * 检测干扰因子
	 * 
	 * @param string $word
	 * 
	 * @return bool
	 */
	protected function checkInterferenceFactor(string $word)
	{
		return in_array($word, $this->interferenceFactors);
	}

	/**
	 * @param $path
	 * @return string
	 */
	protected static function getSensitiveWordPath($path, $update = false)
	{
		static $pathMaps = [];
		if (!class_exists(\Phar::class, false) || !\Phar::running()) {
			return $path;
		}

		$tmpPath = sys_get_temp_dir() ?: '/tmp';
		$filePath = "$tmpPath/" . basename($path);
		clearstatcache();
		
		if ((!isset($pathMaps[$path]) || !is_file($filePath)) || $update === true) {
			file_put_contents($filePath, file_get_contents($path));
			$pathMaps[$path] = $filePath;
		}

		return $pathMaps[$path];
	}
}
