<?php
	error_reporting(E_ALL);
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);

	function hdd($input){
		hd($input);
		die();
	}

	function hd($input){
		echo(h($input) . "<br/>\n");
	}

	function h($input){
		if(is_string($input)){
			$output = htmlspecialchars($input);
			$output = str_replace("\r\n", "\n", $output);
			$output = str_replace("\r", "\n", $output);
			$output = str_replace("\n", "<br/>\n", $output);
			$output = str_replace("\t", '   ', $output);
			$output = str_replace(" ", "&nbsp;", $output);
		} elseif(is_object($input)) {
			$output = (string)$input;
			$output = h($output);
		} else {
			ob_start();
			print_r($input);
			$output = ob_get_clean();
			$output = h($output);
		}
		return $output;
	}

	spl_autoload_register(function($className){
		$file = __DIR__ . '/classes/' . strToLower($className) . '.php';
		if (file_exists($file)){
			require_once($file);
		}
	});

	function streamFile($path, $mimeType=null)
	{
		if (file_exists($path)) {
			if (empty($mimeType)){
				$finfo = finfo_open(FILEINFO_MIME_TYPE);
				$mimeType = finfo_file($finfo, $path);
				finfo_close($finfo);
			}

			// Set headers
			header("Content-Type: " . $mimeType);
			header("Content-Length: " . filesize($path));

			// Clean any output buffers to ensure no accidental whitespace
			// gets prepended to your binary data.
			if (ob_get_level()) ob_end_clean();

			// Stream it directly
			$fp = fopen($path, 'rb');
			if (false !== $fp){
				try{
					fpassthru($fp);
				} finally {
					fclose($fp);
				}
			}
		}
		exit;
	}

	function streamData(string $data, string $mimeType)
	{
		header("Content-Type: " . $mimeType);
		header("Content-Length: " . strlen($data));
		header("Cache-Control: public, max-age=86400");
		if (ob_get_level()) ob_end_clean();
		echo $data;
		exit;
	}

	/**
	 * DomainArt for the requested host, tinted with the first configured color if there is one
	 * and hueOf understands it, else with a hue derived from the domain.
	 */
	function domainArt() : DomainArt
	{
		$host = strtolower(preg_replace('/:\d+$/', '', trim($_SERVER['HTTP_HOST'] ?? '')));
		$hue = null;
		$color = trim(Config::$aesthetics->colors[0] ?? '');
		if ('' !== $color){
			try{
				$hue = DomainArt::hueOf($color);
			} catch (InvalidArgumentException){
				// Not a color name or hex value hueOf understands
			}
		}
		return new DomainArt($host, $hue);
	}


	$content = null;
	$is_dir = false;

	// Handle not-configured condition
	if (empty(Config::$paths->contentRoot)) {
		http_response_code(503);
		$title = "Configuration Needed";
		$content = "#Not configured\nIf you are the site owner please see the g2w.ini file and configure at least paths/g_path, the path to the Gemini or Gopher folder.";
		$content = new GemTextToHTML($content, false, 2, $title, '');
	} elseif (!is_dir(Config::$paths->contentRoot)) {
		http_response_code(500);
		$title = 'Configuration Error';
		$content = '#Configuration Error\nThe capsule source directory is inaccessible.';
		$content = new GemTextToHtml($content, false, 2, $title, '');
	}

	// Handle out of bounds request
	if (is_null($content) && !str_starts_with(Config::$paths->requestedPath, Config::$paths->contentRoot)){
		http_response_code(400);
		$title = 'Bad Request';
		$content = '#Bad Request\nOut of Bounds';
		$content = new GemTextToHTML($content, false, 2, $title, '');
	}

	
	// Handle G2W Files
	if (is_null($content)){
		// CSS
		if ('g2w/g2w.css' == Config::$paths->relativeUri){
			$css_path = Config::$aesthetics->stylesheetPath();
			if (empty($css_path)){
				http_response_code(404);
				$title = "404 Error - Page Not Found";
				$content = "# Error Four Zero Four\nPage Not Found";
				$content = new GemTextToHTML($content, false, 2, $title, '');
			} else {
				streamFile($css_path, "text/css");
			}
		}
		// Icons
		elseif (str_starts_with(Config::$paths->relativeUri, 'g2w/icons/') && str_ends_with(Config::$paths->relativeUri, '.gif')){
			$icon = substr(config::$paths->relativeUri, 10, -4);
			$icon = FolderToHTML::getIconPath($icon);
			if (empty($icon)){
				http_response_code(404);
				$title = '404 Error - Page Not Found';
				$content = "# Error Four Zero Four\nPage Not Found";
				$content = new GemTextToHTML($content, false, 2, $title, '');
			} else {
				streamFile($icon);
				exit();
			}
		}
		// Favicon
		elseif ('g2w/favicon.ico' == Config::$paths->relativeUri){
			$favicon = Config::$aesthetics->themeFile('favicon.ico');
			if (!Config::$aesthetics->useFavicon){
				http_response_code(404);
				$title = '404 Error - Page Not Found';
				$content = "# Error Four Zero Four\nPage Not Found";
				$content = new GemTextToHTML($content, false, 2, $title, '');
			} elseif (file_exists($favicon)){
				// Themes may ship any image format named favicon.ico, so detect the type from its contents
				streamFile($favicon);
				exit();
			} else {
				streamData(domainArt()->faviconIco(), 'image/x-icon');
			}
		}
		// Background
		elseif ('g2w/background.gif' == Config::$paths->relativeUri){
			$background = Config::$aesthetics->themeFile('background.gif');
			$gif = null;
			if (Config::$aesthetics->useBackgroundGif && !file_exists($background)){
				// Smaller than the default tile size, so more of the pattern shows in narrow gaps. null when no raster backend can write GIF
				$gif = domainArt()->background('gif', 32);
			}
			if (!Config::$aesthetics->useBackgroundGif || (!file_exists($background) && null === $gif)){
				http_response_code(404);
				$title = '404 Error - Page Not Found';
				$content = "# Error Four Zero Four\nPage Not Found";
				$content = new GemTextToHTML($content, false, 2, $title, '');
			} elseif (file_exists($background)){
				// Themes may ship any image format named background.gif, so detect the type from its contents
				streamFile($background);
			} else {
				streamData($gif, 'image/gif');
			}
		}
		// Logo
		elseif ('g2w/logo.gif' == Config::$paths->relativeUri){
			$logo = Config::$aesthetics->themeFile('logo.gif');
			$gif = null;
			if (Config::$aesthetics->useLogo && !file_exists($logo)){
				// The domain's identicon, transparent background. null when no raster backend can write GIF
				$gif = domainArt()->favicon(64, 'gif');
			}
			if (!Config::$aesthetics->useLogo || (!file_exists($logo) && null === $gif)){
				http_response_code(404);
				$title = '404 Error - Page Not Found';
				$content = "# Error Four Zero Four\nPage Not Found";
				$content = new GemTextToHTML($content, false, 2, $title, '');
			} elseif (file_exists($logo)){
				// Themes may ship any image format named logo.gif, so detect the type from its contents
				streamFile($logo);
			} else {
				streamData($gif, 'image/gif');
			}
		}
	}

	if (is_null($content)){
		$filename = basename(Config::$paths->requestedPath);
		$ext = strtolower(pathinfo(Config::$paths->requestedPath, PATHINFO_EXTENSION));
		$is_dir = is_dir(Config::$paths->requestedPath);

		// Enforce directory request trailing slash
		if ($is_dir && !str_ends_with(Config::$paths->requestUri, '/')){
			$url = Config::$paths->baseUrl . '/' . Config::$paths->relativeUri . '/';
			http_response_code(301);
			header("Location: {$url}");
			exit;
		}

		// Identify this folder's index file
		$index_file = '';
		foreach (Config::$behaviors->indexFiles as $i){
			$check = Config::$paths->requestedPathDir . DIRECTORY_SEPARATOR . $i;
			if (file_exists($check)){
				$index_file = $i;
				break;
			}
		}
		
		// Handle direct requests of the index file
		if (0 === strcasecmp($index_file, $filename)){
			http_response_code(301);
			header('Location: ' . dirname($_SERVER['REQUEST_URI']) . '/');
			exit;
		}
	}

	// Handle index file
	if ($is_dir && (!empty($index_file))){
		$filename = $index_file . '.' . Config::$behaviors->htmlExt;
		$is_dir = false;
		Config::$paths->requestedPath .= $index_file . '.' . Config::$behaviors->htmlExt;
		$ext = Config::$behaviors->htmlExt;
	}

	// Handle directories
	if (is_null($content) && $is_dir){
		$content = new FolderToHTML(Config::$paths->requestedPath, true, 2);
	}

	// Handle forbidden direct requests
	if (is_null($content)){
		switch ($ext){
		case 'gmi':
		case 'gemini':
			if (!Config::$behaviors->allowOriginalDownload['gmi']){
				header("Location: {$_SERVER['REQUEST_URI']}." . Config::$behaviors->htmlExt, true, 301);
				exit(0);
			}
			break;
		case 'gophermap':
			if (!Config::$behaviors->allowOriginalDownload['gophermap']){
				header("Location: {$_SERVER['REQUEST_URI']}." . Config::$behaviors->htmlExt, true, 301);
				exit(0);
			}
			break;
		case 'txt':
			if (!Config::$behaviors->allowOriginalDownload['gmi']){
				header("Location: {$_SERVER['REQUEST_URI']}." . Config::$behaviors->htmlExt, true, 301);
				exit(0);
			}
			break;
		}
	}
	// Handle conversions
	if (null == $content && $ext == Config::$behaviors->htmlExt){
		$origFile = pathinfo($filename, PATHINFO_FILENAME);
		$origExt = strToLower(pathinfo($origFile, PATHINFO_EXTENSION));
		if (empty($origExt) && 0 === strcasecmp($origFile, 'gophermap')){
			$origExt = 'gophermap';
		}
		$origPath = Config::$paths->requestedPathDir .DIRECTORY_SEPARATOR . $origFile;
		switch($origExt){
		case 'gemini':
		case 'gmi':
			if (file_exists($origPath)){
				$content = new GemTextToHTML($origPath, true, 2);
			}
			break;
		case 'gophermap':
			if (file_exists($origPath)){
				$content = new GophermapToHTML($origPath, true, 2);
			}
			break;
		case 'txt':
			if (file_exists($origPath)){
				$content = new TextToHTML($origPath, true, 2);
			}
			break;
		}
	}

	
	if (is_null($content)){
		streamFile(Config::$paths->requestedPath);
		die;
	}
	else
	{
		$templates = array();
		if (Config::$aesthetics->retroHtml){
			if ((!empty(Config::$aesthetics->theme) && 'default' != Config::$aesthetics->theme)){
				$templates[] = Config::$aesthetics->theme. '/retro.phtml';
			}
			$templates[] = 'default/retro.phtml';
		}
		if ((!empty(Config::$aesthetics->theme) && 'default' != Config::$aesthetics->theme)){
			$templates[] = Config::$aesthetics->theme. '/template.phtml';
		}
		$templates[] = 'default/template.phtml';
		foreach ($templates as $i){
			$template = Config::$paths->basePath . '/themes/' . $i;
			if (file_exists($template)){
				require($template);
				die;
			}
			else
			{
				foreach ($content as $line){
					echo $line . "/n";
				}
			}
		}
	}
