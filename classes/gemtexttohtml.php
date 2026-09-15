<?php

	/**
	 * @brief Converts Gemtext content to HTML, line by line or from a file.
	 *
	 * This class implements the Iterator interface to allow for streaming
	 * conversion of Gemtext, which is particularly useful for large files.
	 * It handles various Gemtext elements such as headings, lists, blockquotes,
	 * preformatted text, links, and plain paragraphs, converting them into
	 * appropriate HTML tags.
	 */
	class GemTextToHTML implements G2WConverter {

		private $gemText = null; ///< Array of Gemtext lines if input is a string.
		private $path = null; ///< Path to the Gemtext file.
		private $index = 0; ///< Current line index for iteration.
		private $fileHandle = null; ///< File handle for reading from a path.
		private $currentLine = null; ///< The currently processed HTML line.
		private $indent = "   "; ///< Indentation string for HTML output.
		private $title = null; ///< The title
		private $description = null; ///< The meta description
		
		private $startIndentLevel = 0; ///< Initial indentation level for HTML.

		private $inList = false; ///< State flag: true if currently inside a list block.
		private $inPre = false; ///< State flag: true if currently inside a preformatted block.
		private $inBlockQuote = false; ///< State flag: true if currently inside a blockquote block.
		private $indentLevel = 0; ///< Current indentation level.

		/**
		 * @brief Constructor for GemTextToHTML.
		 *
		 * Initializes the converter with either a Gemtext string or a file path.
		 *
		 * @param string $input The Gemtext content string or file path.
		 * @param bool $is_path If true, $input is treated as a file path; otherwise, as a string.
		 * @param int $indentLevel The starting indentation level for generated HTML.
		 * @throws InvalidArgumentException If $is_path is true and $input is empty.
		 * @throws RuntimeException If $is_path is true and the file specified by $input is not found or inaccessible.
		 */
		function __construct(
			string $input,
			bool $is_path = false,
			int $indentLevel = 2,
			$title = null,
			$description = null,
		) {
			if ($is_path){
				if (empty($input)){
					throw new InvalidArgumentException('File path cannot be empty.');
				}
				if (!file_exists($input)){
					throw new RuntimeException("File not found or inaccessible {$input}");
				}
				$this->path = $input;
			} else {
				if (null == $input) {
					$input = '';
				}
				$this->gemText = explode("\n", $input);
			}

			$this->indentLevel = $indentLevel;
			$this->startIndentLevel = $indentLevel;
			$this->title = $title;
			$this->description = $description;
		}

		/**
		 * @brief Rewinds the iterator to the first element.
		 *
		 * Resets the internal state, including the line index, current line,
		 * and block state (list, pre, blockquote), and reopens the file handle
		 * if processing a file.
		 */
		public function rewind() : void {
			$this->index = 0;
			$this->currentLine = null;

			$this->inList = false;
			$this->inPre = false;
			$this->inBlockQuote = false;
			$this->indentLevel = $this->startIndentLevel;

			if (!is_null($this->fileHandle)){
				rewind($this->fileHandle);
			} elseif (!empty($this->path) && file_exists($this->path)) {
				$this->fileHandle = fopen($this->path, 'r');
			}
			$this->readNextLine();
		}

		/**
		 * @brief Returns the current element.
		 *
		 * @return mixed The current HTML-formatted line.
		 */
		public function current() : mixed {
			if (0 === $this->index && is_null($this->currentLine)){
				$this->rewind();
			}
			return $this->currentLine;
		}

		/**
		 * @brief Returns the key of the current element.
		 *
		 * @return mixed The current line index.
		 */
		public function key() : mixed {
			return $this->index;
		}

		/**
		 * @brief Moves forward to the next element.
		 *
		 * Increments the internal index and reads the next line, converting it to HTML.
		 */
		public function next() : void {
			$this->index++;
			$this->readNextLine();
		}

		/**
		 * @brief Checks if the current position is valid.
		 *
		 * @return bool True if there is a current line, false otherwise.
		 */
		public function valid() : bool {
			if (0 === $this->index && is_null($this->currentLine)){
				$this->rewind();
			}
			return !is_null($this->currentLine);
		}

		/**
		 * @brief Reads the next raw Gemtext line and converts it to HTML.
		 *
		 * This private method fetches the next line from either the internal array
		 * or the file handle, processes it through gemLineToHtml(), and stores
		 * the result in $currentLine. It also handles end-of-file block cleanup.
		 */
		private function readNextLine() : void {
			$this->currentLine = null;
			$rawLine = false;

			// fetch the raw data from whichever source we are using
			if (!is_null($this->gemText)){
				if (array_key_exists($this->index, $this->gemText)){
					$rawLine = $this->gemText[$this->index];
				}
			} elseif (!is_null($this->fileHandle)) {
				$rawLine = fgets($this->fileHandle);
				if (false === $rawLine) {
					fclose($this->fileHandle);
					$this->fileHandle = null;
				}
			}

			// if we have data then transform and cache it
			if (false !== $rawLine) {
				$this->currentLine = $this->gemLineToHtml($rawLine);
			} else {
				// EOF Cleanup of any blocks we might be in
				$cleanup = '';
				if ($this->inList){
					$this->indentLevel--;
					$cleanup .= str_repeat($this->indent, max(0, $this->indentLevel)) . "</ul>\n";
					$this->inList = false;
				}
				if ($this->inBlockQuote) {
					$this->indentLevel--;
					$cleanup .= $this->getIndentation() . "</blockquote>\n";
					$this->inBlockQuote = false;
				}
				if (!empty($cleanup)){
					$this->currentLine = $cleanup;
				}
			}
		}

		/**
		 * @brief Generates the current indentation string based on the current indent level.
		 *
		 * @return string The indentation string (e.g., tabs or spaces).
		 */
		private function getIndentation() {
			return str_repeat($this->indent, max(0, $this->indentLevel));
		}

		/**
		 * @brief Converts a single Gemtext line into its corresponding HTML representation.
		 *
		 * This method parses the line, manages block states (preformatted, list, blockquote),
		 * and applies appropriate HTML tags and escaping.
		 *
		 * @param string $line The raw Gemtext line to convert.
		 * @return string The HTML representation of the line.
		 */
		private function gemLineToHtml($line) {
			$content = trim($line);
			$html = "";

			// Any blocks to exit?
			if ($this->inList && !str_starts_with($content, '* ')){
				$this->indentLevel--;
				$html .= str_repeat($this->indent, max(0, $this->indentLevel)) . "</ul>\n";
				$this->inList = false;
			}
			if ($this->inBlockQuote && !str_starts_with($content, '>')){
				$this->indentLevel--;
				$html .= $this->getIndentation() . "</blockquote>\n";
				$this->inBlockQuote = false;
			}

			// Handle Preformatted Toggle (```)
			if (str_starts_with($content, '```')) {
				$this->inPre = !$this->inPre;

				if ($this->inPre) {
					// Parse Hint and Alt-Text
					$hintFull = trim(substr($content, 3));
					$classAttr = 'class="gem"';
					$ariaAttr = '';
					$safeHint = '';
					if (!empty($hintFull)) {
						$safeHint = htmlspecialchars($hintFull);
						$ariaAttr = ' aria-label="' . $safeHint . '"';

						// First word is the "language" class
						$parts = explode(' ', $hintFull, 2);
						$classAttr = 'class="gem language-' . htmlspecialchars($parts[0]) . '"';
					}
					// visible hint label
					if (!empty($safeHint)){
						$html .= $this->getIndentation() . '<small class="gem">' . $safeHint . "</small>\n";
					}
					// Opening block
					$html .= $this->getIndentation() . "<pre{$ariaAttr} class=\"gem\"><code {$classAttr}>\n";
					$this->indentLevel++;
				} else {
					// Closing block
					$this->indentLevel--;
					$html .= $this->getIndentation() . "</code></pre>\n";
				}
				return $html;
			}

			if (!$this->inPre){

				// Any blocks to enter?
				if (str_starts_with($content, '* ') && !$this->inList){
					$html .= $this->getIndentation() . "<ul class=\"gem\">\n";
					$this->indentLevel++;
					$this->inList = true;
				}
				if (str_starts_with($content, '>') && !$this->inBlockQuote){
					$html .= $this->getIndentation() . "<blockquote class=\"gem\">\n";
					$this->indentLevel++;
					$this->inBlockQuote = true;
				}
			}

			// Preformatted Content
			if ($this->inPre) {
				$html .= htmlspecialchars(rtrim($line, "\r\n"))."\n"; //back to $line to preserve beginning/end whitespace
			}

			// List Content
			elseif ($this->inList) {
				$content = ltrim(substr($content, 2));
				$html .= $this->getIndentation() . '<li class="gem">' . htmlspecialchars($content) . "</li>\n";
			}

			// BlockQuote Content
			else if ($this->inBlockQuote){
				$content = ltrim(substr($content, 1));
				$html .= $this->getIndentation() . '<p class="gem_block">' . htmlspecialchars($content) . "</p>\n";
			}

			// Heading Content
			elseif (str_starts_with($content, '#')) {
				$level = 0;
				while (isset($content[$level]) && $content[$level] === '#' && 4 > $level) $level++;
				if (4 > $level){
					$content = ltrim(substr($content, $level));
					$html .= $this->getIndentation() . "<h{$level} class=\"gem\">" . htmlspecialchars($content) . "</h$level>\n";
				} else {
					$html .= $this->getIndentation() . '<p class="gem">'. htmlspecialchars($content) . "</p>";
				}
			}

			// Link Content
			elseif (str_starts_with($content, '=>')) {
				$linkParts = preg_split('/\s+/', ltrim(substr($content, 2)), 2);
				$url = $linkParts[0] ?? '';
				$url = $this->rewriteLink(url: $url, extension: Config::$behaviors->htmlExt);
				$label = $linkParts[1] ?? $url;
				$html .= $this->getIndentation() . "<p class=\"gem_link\"><a class=\"gem\" href=\"" . htmlspecialchars($url) . "\">" . htmlspecialchars($label) . "</a></p>\n";
			}

			// BlockQuote Content (handled in state machine above, but this catches leading > on a non-blockquote line)
			elseif ($this->inBlockQuote || str_starts_with($content, '>')) {
				$content = ltrim(substr($content, 1));
				$html .= $this->getIndentation() . '<p class="gem_block">' . htmlspecialchars($content) . "</p>\n";
			}

			// Blank Lines
			else if (empty(trim($line))){ //going back to $line because $content might have had a tag stripped
				$html .= $this->getIndentation() . "<br/>\n";
			}

			// Plain Content
			elseif (!empty($content)) {
				$html .= $this->getIndentation() . '<p class="gem">' . htmlspecialchars($content) . "</p>\n";
			}

			return $html;
		}

		/**
		 * @brief Rewrites a given URL to include the specified HTML extension if it's a Gemtext file.
		 *
		 * This method intelligently modifies relative links pointing to Gemtext files
		 * (e.g., .gmi, .gemini) by appending an HTML extension, making them suitable
		 * for web browsers. Absolute URLs or other file types are left unchanged.
		 *
		 * @param string $url The URL to rewrite.
		 * @param string $extension The HTML extension to append (e.g., '.html').
		 * @return string The rewritten URL.
		 */
		private function rewriteLink($url, $extension = 'html') {
			$parts = parse_url($url);

			// If it has a scheme (https, gemini, etc.), it's absolute. Leave it alone.
			if (isset($parts['scheme'])) {
				return $url;
			}

			// It's relative. Get the path.
			$path = $parts['path'] ?? '';
			$query = isset($parts['query']) ? '?' . $parts['query'] : '';
			$fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

			// Check the extension of the path
			$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
			$filename = basename($path);

			// List of extensions we want to "web-ify"
			$sourceExts = ['gmi', 'gemini', 'gmni'];

			if (in_array($ext, $sourceExts)) {
				// Change /docs/about.gmi to /docs/about.gmi.html
				return $path . '.' . $extension . $query . $fragment;
			}

			// Special case for Gophermaps (often no extension)
			if ($filename === 'gophermap' || str_starts_with($filename, '.gophermap')) {
				return $path . $extension . $query . $fragment;
			}

			// Otherwise, it's a binary, image, or directory—return as-is
			return $url;
		}

		/**
		 * @brief Extracts the main title from the Gemtext content.
		 *
		 * It searches for the lowest-level heading (H1, H2, or H3) and returns its content.
		 * If no heading is found, it falls back to the provided fallback title.
		 *
		 * @param string|null $fallback An optional fallback title if no title is found in the content.
		 * @return string The extracted (and HTML-escaped) title, or the fallback.
		 */
		function getTitle($fallback = null) : string {
			if (is_null($this->title)){
				$title = null;
				$level = 5;
				
				$content = null;
				if (is_null($this->gemText))
				{
					if (!empty($this->path)){
						if (file_exists($this->path)){
							try{
								$content = new SplFileObject($this->path);
							} catch (Exception) {
								$content = null;
							}
						}
					}
				} else {
					$content = $this->gemText;
				}
				
				try{
					foreach ($content as $line){
						$line = str_replace("\r", ' ', $line);
						$line = trim($line, " \n");
						$last = '';
						while ($line != '' && $line != $last){
							$last = $line;
							$line = str_replace('  ', ' ', $line);
						}
						$this_level = strspn($line, '#');
						$this_level = ($this_level > 0 && $this_level < 4) ? $this_level : 4;
						if ($this_level < $level && '' != $line){
							$level = $this_level;
							$title = ltrim($line, ' #');
							if (1 == $level){
								break;
							}
						}
					}
				} catch (Exception){
					$title = null;
				}
				
				$content = null;
				$title = htmlspecialchars($title);
				if (!empty($title)){
					$this->title = $title;
				}
			}
			return empty($this->title) ? $fallback : $this->title;
		}

		/**
		 * @brief Extracts a description from the Gemtext content.
		 *
		 * It attempts to find a suitable descriptive text from the Gemtext,
		 * skipping headings and preformatted blocks, and truncates it to a maximum
		 * number of characters.
		 *
		 * @param int $maxChars The maximum number of characters for the description.
		 * @return string The extracted (and HTML-escaped) description.
		 */
		function getDescription($maxChars=160, $fallback=null) : string {
			if (null == $this->description){
				$description = '';
				$accept_level = -1;
				$in_pre = false;

				$content = null;
				if (is_null($this->gemText))
				{
					if (!empty($this->path)){
						if (file_exists($this->path)){
							try{
								$content = new SplFileObject($this->path);
							} catch (Exception) {
								$content = null;
							}
						}
					}
				} else {
					$content = $this->gemText;
				}

				try{
					foreach ($content as $line){
						$line = str_replace("\r", ' ', $line);
						$line = trim($line, " \n");
						$last = '';
						while ($line != '' && $line != $last){
							$last = $line;
							$line = str_replace('  ', ' ', $line);
						}
						if (str_starts_with($line, "```")){
							$in_pre = !$in_pre;
							continue;
						}
						if ($in_pre){
							continue;
						}
						$level = strspn($line, '#');
						$level = 0 == $level ? 4 : $level;
						$line = ltrim($line, ' #');
						if (-1 != $accept_level && $level != $accept_level){
							break;
						} elseif ('' == $line){
							continue;
						} else {
							if (null == $description){
								$description = $line;
							}
							if (1 < $level){
								if (-1 == $accept_level){
									$accept_level = $level;
									$description = '';
								}
								if ($level == $accept_level){
									$description = "{$description} {$line}";
									if (strlen($description) > $maxChars){
										$description = substr($description, 0, $maxChars);
										$description = trim($description);
										break;
									}
								}
							}
						}
					}
				} catch (Exception){
				}
				$description = htmlspecialchars($description);
				$this->description = $description;
			}
			
			return empty($description) ? $fallback : $this->description;
		}

		/**
		 * @brief Streams the converted HTML output to the browser.
		 *
		 * Iterates through the Gemtext content, converting each line to HTML
		 * and echoing it to the output buffer in chunks to manage memory
		 * efficiently, especially for large files.
		 *
		 * @param int $buffer_size The size of the buffer (in bytes) before flushing output.
		 */
		public function stream() : void {
			$buffer = '';
			foreach ($this as $line){
				$buffer .= $line;
				if (strlen($buffer) >= Config::$advanced->bufferSize){
					echo $buffer;
					$buffer = '';
					flush();
				}
			}
			if ($buffer !== '') {
				echo $buffer;
				flush();
			}
		}


	}
