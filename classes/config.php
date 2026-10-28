<?php

	class G2WConfig{
		public function list() : string{
			$output = '';
			$reflection = new ReflectionClass(static::class);
			foreach ($reflection->getProperties(ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PUBLIC) as $prop) {
				$prop->setAccessible(true);
				$name = $prop->getName();
				$value = $this->$name;
				$output .= "\t" . $name . ' = ';
				if (is_array($value) || is_object($value)){
					ob_start();
					print_r($value);
					$outVal = ob_get_clean();
					$outVal = explode("\n", $outVal);
					$outStr = '';
					foreach ($outVal as $o){
						$outStr .= empty($outStr) ? '' : "\n";
						$outStr .= "\t{$o}";
					}
					$output .= trim($outStr);
				} else {
					$output .= $value;
				}
				$output .= "\n";				
	 		}
			return $output;
		}
	}
	
	class ConfigPaths extends G2WConfig{

		/**
		 * File path to the ini file
		 */
		public string $iniFile = '';

		/**
		 * File path to content, must be set in ini by the user
		 */
		public string $contentRoot = ''; //formerly g_root

		/**
		 * Path within the url to g2w
		 */
		public string $baseDir;

		/**
		 * File path to the requested content
		 */
		public string $requestedPath;

		/**
		 * Directory path of the requested content
		 */
		public string $requestedPathDir;
		
		
		/**
		 * URL to the base of G2W
		 */
		public string $baseUrl;

		/**
		 * Path to the base of G2W
		 */
		public string $basePath;
		 
		
		/**
		 * Requested sub-path within g2w
		 */
		public string $relativeUri;

		/**
		 * Requested path including both to and within g2w but not domain
		 */
		public string $requestUri;

		/**
		 * Normalizes a path, resolving .. and . without requiring the file to exist.
		 */
		public static function normalizePath($path) {
			$path = urldecode($path);
			$path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
			$parts = explode(DIRECTORY_SEPARATOR, $path);
			$safe = [];
			foreach ($parts as $part) {
				if ($part === '.' || $part === '') continue;
				if ($part === '..') {
					array_pop($safe); // Step back one directory
				} else {
					$safe[] = $part;
				}
			}
			// Return the absolute-looking string
			return DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $safe);
		}

	}

	class ConfigBehaviors extends G2WConfig {
		/**
		 * List folders in a directory when there is no index
		 */
		public bool $allowDirectoryListing = true;

		/**
		 * Allow the direct downlload of original files that G2W translates to html
		 */
		public array $allowOriginalDownload = [ 0=> true ];

		/**
		 * File names that will act as index files for their folders
		 */
		public array $indexFiles = [
			'index.gmi',
			'main.gmi',
			'gophermap',
			'.gophermap',
			'index.txt',
		];

		/**
		 * Files which g2w should ignore, do not server to viewers.
		 */
		public array|string $ignoreFiles = [
			'.htaccess',
			'config.ini',
			'*.log'
		];

		/**
		 * What extension should be used for 'virtual' html files.
		 */
		public string $htmlExt = 'html';
	}

	class ConfigAesthetics extends G2WConfig{
		/**
		 * Title to use for pages where one cannot be easily implied
		 */
		public string $siteTitle = '';

		/**
		 * Use older style html code for viewing in retro browsers
		 */
		public string $retroHtml = '';

		/**
		 * Name of theme to apply
		 */
		public string $theme = '';

		/**
		 * Text or html to include in the footer of all pages
		 */
		public string $footerText = '';

		/**
		 * Include a link to the favicon in the page head, either the theme's favicon.ico or one generated from the domain
		 */
		public bool $useFavicon = false;

		/**
		 * Tile the page background with an image, either the theme's background.gif or one generated from the domain
		 */
		public bool $useBackgroundGif = false;

		/**
		 * Show a logo in the page header, either the theme's logo.gif or one generated from the domain
		 */
		public bool $useLogo = false;

		/**
		 * A list of colors to be consumed by the theme's template.phtml. Each theme decides how many it uses and what each index means.
		 */
		public array $colors = array();
		
		/**
		 * Only characters that can appear in a CSS color, so a value can't break out of the rule
		 */
		private static function isCssColor(string $value) : bool{
			return '' !== trim($value) && 1 === preg_match('/^[#\w\s(),.%+\/-]+$/', $value);
		}

		/**
		 * Build a :root rule setting a theme's CSS variables from $colors, matched by index.
		 * Extra colors are ignored, missing or invalid ones leave the theme's default.
		 * Returns an empty string when there is nothing to override.
		 */
		public function colorOverrides(array $variableNames) : string{
			$output = '';
			foreach (array_values($variableNames) as $i=> $name){
				$value = trim($this->colors[$i] ?? '');
				if (self::isCssColor($value)){
					$output .= " $name: $value;";
				}
			}
			return '' === $output ? '' : ":root {{$output} }";
		}

		/**
		 * Cache busting query string from the newer of the config and $path, if $path exists.
		 * Empty when the config's lastmod is unknown.
		 * @internal Used by the get*Link() methods and FolderToHTML, not meant for templates
		 */
		public static function cacheBuster(?string $path = null) : string{
			if (Config::$lastmod <= 0){
				return '';
			}
			$lastmod = Config::$lastmod;
			if (!empty($path) && file_exists($path)){
				$lastmod = max(filemtime($path), $lastmod);
			}
			return "?v={$lastmod}";
		}

		/**
		 * File path of $filename in the selected theme's folder, whether or not it exists
		 * @internal Used by the get*Link() methods and g2w.php's routes, not meant for templates
		 */
		public function themeFile(string $filename) : string{
			return Config::$paths->basePath . '/themes/' . $this->theme . '/' . $filename;
		}

		/**
		 * File path of the stylesheet served at g2w/g2w.css: the theme's, falling back to the default theme's,
		 * preferring retro.css over g2w.css when retroHtml is on. null if none exists.
		 * @internal Used by getStylesheetLink() and g2w.php's g2w/g2w.css route, not meant for templates
		 */
		public function stylesheetPath() : ?string{
			$filenames = $this->retroHtml ? ['retro.css', 'g2w.css'] : ['g2w.css'];
			foreach ($filenames as $filename){
				if (!empty($this->theme) && 0 !== strcasecmp($this->theme, 'default') && file_exists($this->themeFile($filename))){
					return $this->themeFile($filename);
				}
				if (file_exists(Config::$paths->basePath . '/themes/default/' . $filename)){
					return Config::$paths->basePath . '/themes/default/' . $filename;
				}
			}
			return null;
		}

		/**
		 * <link> tag for the stylesheet, cache busted by whichever file stylesheetPath() serves
		 */
		public function getStylesheetLink() : string{
			$url = Config::$paths->baseDir . 'g2w/g2w.css' . self::cacheBuster($this->stylesheetPath());
			return "<link rel=\"stylesheet\" type=\"text/css\" href=\"{$url}\" />";
		}

		/**
		 * <link> tag for the favicon, or an empty string when useFavicon is off
		 */
		public function getFaviconLink() : string{
			$output = '';
			if ($this->useFavicon){
				$output = Config::$paths->baseDir . 'g2w/favicon.ico' . self::cacheBuster($this->themeFile('favicon.ico'));
				$output = "<link rel=\"icon\" href=\"{$output}\" />";
			}
			return $output;
		}

		/**
		 * <style> tag tiling the page background with background.gif, or an empty string when useBackgroundGif is off
		 */
		public function getBackgroundLink() : string{
			$output = '';
			if ($this->useBackgroundGif){
				$output = Config::$paths->baseDir . 'g2w/background.gif' . self::cacheBuster($this->themeFile('background.gif'));
				$output = "<style>body { background-image: url(\"{$output}\"); background-repeat: repeat; }</style>";
			}
			return $output;
		}

		/**
		 * <img> tag for the logo, or an empty string when useLogo is off
		 */
		public function getLogoLink() : string{
			$output = '';
			if ($this->useLogo){
				$output = Config::$paths->baseDir . 'g2w/logo.gif' . self::cacheBuster($this->themeFile('logo.gif'));
				$output = "<img class=\"logo\" src=\"{$output}\" alt=\"\" />";
			}
			return $output;
		}
	}
	
	class ConfigSEO extends G2WConfig{
		/**
		 * Robots Policy/ies
		 */
		public array $robotsPolicy = [
			0=> "index, follow",
		];

		/**
		 * Include a summary in the headers
		 */
		public bool $useMetaDescription = false;
		
		/**
		 * Where to cut off the description
		 */
		public int $maxDescriptionChars = 160;
	}

	class ConfigGopher extends G2WConfig{
		/**
		 * Separator character between items in gophermap file
		 */
		public string $gophermapSeparator = "\t";

		/**
		 * Surround items in gophermaps with brackets
		 */
		public bool $gophermapUseBrackets = false;
	}

	class ConfigAdvanced extends G2WConfig{
		/**
		 * Size after which output buffer should flush
		 */
		public int $bufferSize = 8192;
	}

	class Config {

		public static ConfigPaths $paths;

		public static int|bool $lastmod = false; 
		
		public static ConfigBehaviors $behaviors;

		public static ConfigAesthetics $aesthetics;

		public static ConfigSEO $seo;
		
		public static ConfigGopher $gopher;

		public static ConfigAdvanced $advanced;

		public static function PropertyExists(string $property) : bool{
			$output = false;
			$reflection = new ReflectionClass(static::class);
			if ($reflection->hasProperty($property) && $reflection->getProperty($property)->isStatic()) {
				$output = true;
			}
			return $output;
		}

		public static function list() : string{
			$output = '';
			$reflection = new ReflectionClass(static::class);
			foreach ($reflection->getProperties(ReflectionProperty::IS_STATIC | ReflectionProperty::IS_PUBLIC) as $prop) {
				$prop->setAccessible(true);
				$value = $prop->getValue();
				$output .= $prop->getName() . " = ";
				if ($value instanceof G2WConfig){
					$output .= "[\n".$value->list(1)."]";
				} else if (is_array($value) || is_object($value)){
					ob_start();
					print_r($value);
					$outVal = ob_get_clean();
					$outVal = explode("\n", $outVal);
				} else {
					$output .= $value;
				}
				$output .= "\n";				
	 		}
			return $output;
		}
		
	}

	(static function(){

		Config::$paths = new ConfigPaths();
		Config::$behaviors = new ConfigBehaviors();
		Config::$aesthetics = new ConfigAesthetics();
		Config::$seo = new ConfigSEO();
		Config::$gopher = new ConfigGopher();
		Config::$advanced = new ConfigAdvanced();
		
		Config::$paths->iniFile = dirname(__DIR__) . '/g2w.ini';
   
		if (file_exists(Config::$paths->iniFile)) {
			Config::$lastmod = filemtime(Config::$paths->iniFile);
			$ini = parse_ini_file(Config::$paths->iniFile, true);
			if ($ini) {
				foreach ($ini as $outer_key=> $outer_value){
					if (Config::PropertyExists($outer_key)){
						if (is_array($ini[$outer_key])){
							if (is_object(Config::$$outer_key)){
								foreach ($ini[$outer_key] as $inner_key=> $inner_value){
									if (property_exists(Config::$$outer_key, $inner_key)){
										if (is_array(Config::$$outer_key->$inner_key)){
											if (is_array($inner_value)){
												Config::$$outer_key->$inner_key = $inner_value;
											} else {
												Config::$$outer_key->$inner_key = array_map('trim', explode(',', $inner_value));
											}
										}
										else {
											Config::$$outer_key->$inner_key = $inner_value;
										}
									}
								}
							} else if(is_array(Config::$$outer_key)){
								foreach ($ini[$outer_key] as $inner_key=> $inner_value){
									if (array_key_exists($inner_key, Config::$$outer_key)){
										Config::$$outer_key[$inner_key] = $inner_value;
									}
								}
							}
						} else {
							if (!is_array(Config::$$outer_key)){
								Config::$$outer_key = $outer_value;
							}
						}
					}
				}
			}
		}

		if (is_string(Config::$behaviors->indexFiles)){
			Config::$behaviors->indexFiles = array_map('trim', explode(',', Config::$behaviors->indexFiles));
		}
		if (is_string(Config::$behaviors->ignoreFiles)){
			Config::$behaviors->ignoreFiles = array_map('trim', explode(',', Config::$behaviors->ignoreFiles));
		}
		if (empty(Config::$behaviors->htmlExt)){
			Config::$behaviors->htmlExt = Config::$aesthetics->retroHtml ? '.htm' : '.html';
		}

		if (!empty(Config::$paths->contentRoot)){
			Config::$paths->contentRoot = rtrim(Config::$paths->contentRoot, DIRECTORY_SEPARATOR);
		}

		$baseDir = rtrim(dirname(trim($_SERVER['SCRIPT_NAME'])), '/\\');
		Config::$paths->baseDir = $baseDir . '/';
		Config::$paths->requestUri = parse_url(trim($_SERVER['REQUEST_URI']), PHP_URL_PATH);

		if (str_starts_with(Config::$paths->requestUri, $baseDir)) {
			Config::$paths->relativeUri = substr(Config::$paths->requestUri, strlen($baseDir));
		} else {
			Config::$paths->relativeUri = Config::$paths->requestUri;
		}
		Config::$paths->relativeUri = ltrim(Config::$paths->relativeUri, '/');
		Config::$paths->requestedPath = ConfigPaths::normalizePath(Config::$paths->contentRoot . DIRECTORY_SEPARATOR . Config::$paths->relativeUri);
		Config::$paths->requestedPathDir = is_dir(Config::$paths->requestedPath) ? Config::$paths->requestedPath : dirname(Config::$paths->requestedPath);
		Config::$paths->baseUrl = (!empty(trim($_SERVER['HTTPS'])) && trim($_SERVER['HTTPS']) !== 'off' || trim($_SERVER['SERVER_PORT']) == 443) ? "https://" : "http://";
		Config::$paths->baseUrl .= trim($_SERVER['HTTP_HOST']);
		Config::$paths->baseUrl .= Config::$paths->baseDir;
		Config::$paths->baseUrl = rtrim(Config::$paths->baseUrl, '/');
		Config::$paths->basePath = dirname(__DIR__);
		
		$defBehavior = Config::$behaviors->allowOriginalDownload[0];
		if (!array_key_exists('gmi', Config::$behaviors->allowOriginalDownload)){
			Config::$behaviors->allowOriginalDownload['gmi'] = $defBehavior;
		}
		if (!array_key_exists('gophermap', Config::$behaviors->allowOriginalDownload)){
			Config::$behaviors->allowOriginalDownload['gophermap'] = $defBehavior;
		}
		if (!array_key_exists('txt', Config::$behaviors->allowOriginalDownload)){
			Config::$behaviors->allowOriginalDownload['txt'] = $defBehavior;
		}
		if (!array_key_exists('desktop', Config::$behaviors->allowOriginalDownload)){
			Config::$behaviors->allowOriginalDownload['desktop'] = $defBehavior;
		}
	})();
