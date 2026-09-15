<?php

	require_once('config.php');
	
	/**
	 * @brief Converts Gophermap content to HTML, line by line or from a file.
	 *
	 * This class implements the Iterator interface to allow for streaming
	 * conversion of Gophermap content, which is particularly useful for large files.
	 * It handles various Gophermap item types, converting them into appropriate HTML
	 * links and text representations within a <pre> block.
	 */
	class GophermapToHTML implements G2WConverter {
		private $gophermapContent = null; ///< Array of Gophermap lines if input is a string.
		private $path = null; ///< Path to the Gophermap file.
		private $index = 0; ///< Current line index for iteration.
		private $fileHandle = null; ///< File handle for reading from a path.
		private $currentHtmlLine = null; ///< The currently processed HTML line.

		private $startIndentLevel = 0; ///< Initial indentation level for HTML.
		private $indentLevel = 0; ///< Current indentation level.
		private $title = null;
		private $description = null;
		
		/**
		 * @brief Constructor for GophermapToHTML.
		 *
		 * Initializes the converter with either a Gophermap string or a file path,
		 * along with configuration and base URL details.
		 *
		 * @param string $input The Gophermap content string or file path.
		 * @param bool $is_path If true, $input is treated as a file path; otherwise, as a string.
		 * @param int $indentLevel The base number of indents to apply.
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
				$input = str_replace(["\r\n", "\r"], "\n", $input);
				$this->gophermapContent = explode("\n", $input);
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
		 * and reopens the file handle if processing a file.
		 */
		public function rewind() : void {
			$this->index = 0;
			$this->currentHtmlLine = null;
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
			if (0 === $this->index && is_null($this->currentHtmlLine)){
				$this->rewind();
			}
			return $this->currentHtmlLine;
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
			if (0 === $this->index && is_null($this->currentHtmlLine)){
				$this->rewind();
			}
			return !is_null($this->currentHtmlLine);
		}

		/**
		 * @brief Reads the next raw Gophermap line and converts it to HTML.
		 *
		 * This private method fetches the next line from either the internal array
		 * or the file handle, processes it through gopherLineToHtml(), and stores
		 * the result in $currentHtmlLine.
		 */
		private function readNextLine() : void {
			$this->currentHtmlLine = null;
			$rawLine = false;

			// fetch the raw data from whichever source we are using
			if (!is_null($this->gophermapContent)){
				if (array_key_exists($this->index, $this->gophermapContent)){
					$rawLine = $this->gophermapContent[$this->index];
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
				$this->currentHtmlLine = $this->getIndentation() . $this->gopherLineToHtml($rawLine) . "\n";
			}
		}

		/**
		 * @brief Generates the current indentation string based on the current indent level.
		 *
		 * @return string The indentation string (e.g., tabs or spaces).
		 */
		private function getIndentation() {
			return str_repeat("\t", max(0, $this->indentLevel));
		}

		/**
		 * @brief Converts a single Gophermap line into its corresponding HTML representation.
		 *
		 * @param string $line The raw Gophermap line to convert.
		 * @return string The HTML representation of the line.
		 */
		private function gopherLineToHtml($line) {
			// Gopher lines are terminated by CRLF (\r\n)
			$line = rtrim($line, "\r\n");

			// If it's an empty line, just give us a break
			if (empty($line)) return "<br/>";

			$type = $line[0]; // The first character
			$separator = Config::$gopher->gophermapUseBrackets ?? "\t"; // Default separator
			$parts = explode($separator, substr($line, 1));
			// Gopher requires at least 4 fields for a valid link
			$name     = $parts[0] ?? '';
			$selector = $parts[1] ?? '';
			$host     = $parts[2] ?? '';
			$port     = $parts[3] ?? '70';

			$selector = ltrim($selector, '/');

			if (1 == strlen($line) || 'i' == $type){
				return $name . "<br/>";
			} else {
				switch($type){
				case '2': //CCSO Phonebook query (unsupported)
				case '6': //uuencoded file (not implmented yet)
				case '7': //search query (unsupported)
				case '+': //mirror (unsupported)
				case '#': //comments
					return '';
				case '3': //error
					return "<div class=\"error\">{$name}</div>";
				case '0': //txt
					if ($this->isThisHost($host)){
						return '(txt) - <a href="'. Config::$paths->baseDir . "/{$selector}\">{$name}</a><br/>";
					} else {
						return "(txt) - <a href=\"gopher://{$host}:{$port}/0/{$selector}/\">{$name}</a><br/>";
					}
				case '1': //dir or gopher submenu
					$file_path = (Config::$paths->contentRoot ?? '') . "/{$selector}";
					if (file_exists($file_path) && is_file($file_path)){
						$selector .= (Config::$behaviors->htmlExt ?? '.html');
					}
					if ($this->isThisHost($host)){
						return '(mnu) - <a href="' . Config::$paths->baseUrl . "/{$selector}\">{$name}</a><br/>";
					} else {
						return "(mnu) - <a href=\"gopher://{$host}:{$port}/1/{$selector}\">{$name}</a><br/>";
					}
				case '4': //mac binhex
					if ($this->isThisHost($host)){
						return '(hqx) - <a href="' . Config::$paths->baseUrl . "/{$selector}\">{$name}</a><br/>";
					} else {
						return "(hqx) - <a href=\"gopher://{$host}:{$port}/4/{$selector}\">{$name}</a><br/>";
					}
				case '5': //zip
					if ($this->isThisHost($host)){
						return '(zip) - <a href=' . Config::$paths->baseUrl . "/{$selector}\">{$name}</a><br/>";
					} else {
						return "(zip) - <a href=\"gopher://{$host}:{$port}/5/{$selector}\">{$name}</a><br/>";
					}
				case '8': //telnet
					return "(tel) - <a href=\"telnet://{$host}" . (23==$port ? '' : ":{$port}") . "/{$selector}\">{$name}</a><br/>";
				case '9': //binary
					if ($this->isThisHost($host)){
						return '(bin) - <a href="' . Config::$paths->baseUrl . "/{$selector}\">{$name}</a><br/>";
					} else {
						return "(bin) - <a href=\"gopher://{$host}:{$port}/9/{$selector}\">{$name}</a><br/>";
					}
				case '<': //sound file
					if ($this->isThisHost($host)){
						return '(snd) - <a href="' . Config::$paths->baseUrl . "/{$selector}\">{$name}</a><br/>";
					} else {
						return "(snd) - <a href=\"gopher://{$host}:{$port}/</{$selector}\">{$name}</a><br/>";
					}
				case ';': //video file
					if ($this->isThisHost($host)){
						return '(vid) - <a href="' . Config::$paths->baseUrl . "/{$selector}\">{$name}</a><br/>";
					} else {
						return "(vid) - <a href=\"gopher://{$host}:{$port}/;/{$selector}\">{$name}</a><br/>";
					}
				case ':': //bitmap image
					if ($this->isThisHost($host)){
						return '(bmp) - <a href="' . Config::$paths->baseUrl . "/{$selector}\">{$name}</a><br/>";
					} else {
						return "(bmp) - <a href=\"gopher://{$host}:{$port}/:/{$selector}\">{$name}</a><br/>";
					}
				case 'd': //document (pdf, word, etc...)
					if ($this->isThisHost($host)){
						return '(doc) - <a href="' . Config::$paths->baseUrl . "/{$selector}\">{$name}</a><br/>";
					} else {
						return "(doc) - <a href=\"gopher://{$host}:{$port}/d/{$selector}\">{$name}</a>";
					}
				case 'g': //gif
					if ($this->isThisHost($host)){
						return '(gif) - <a href="' . Config::$paths->baseUrl . "/{$selector}\">{$name}</a><br/>";
					} else {
						return "(gif) - <a href=\"gopher://{$host}:{$port}/g/{$selector}\">{$name}</a><br/>";
					}
				case 'h': //html
					if ($this->isThisHost($host)){
						return '(htm) - <a href="' . Config::$paths->baseUrl . "/{$selector}\">{$name}</a><br/>";
					} else {
						return "(htm) - <a href=\"gopher://{$host}:{$port}/h/{$selector}\">{$name}</a><br/>";
					}
				case 'I': //image (non-gif)
					if ($this->isThisHost($host)){
						return '(img) - <a href="' . Config::$paths->baseUrl . "/{$selector}\">{$name}</a><br/>";
					} else {
						return "(img) - <a href=\"gopher://{$host}:{$port}/I/{$selector}\">{$name}</a><br/>";
					}
				case 'P': //pdf
					if ($this->isThisHost($host)){
						return '(pdf) - <a href="' . Config::$paths->baseUrl . "/{$selector}\">{$name}</a><br/>";
					} else {
						return "(pdf) - <a href=\"gopher://{$host}:{$port}/P/{$selector}\">{$name}</a><br/>";
					}
				case 's': //sound file
					if ($this->isThisHost($host)){
						return '(snd) - <a href="' . Config::$paths->baseUrl . "/{$selector}\">{$name}</a><br/>";
					} else {
						return "(snd) - <a href=\"gopher://{$host}:{$port}/s/{$selector}\">{$name}</a><br/>";
					}
				case 'T': //TN3270
					return "(tn3270) - <a href=\"tn3270://{$host}" . (23==$port ? '' : ":{$port}") . "/{$selector}\">{$name}</a><br/>";
				case 'h': //web link
					$url = $selector;
					if (str_starts_with(strtolower($url), 'url:')){
						$url = substr($url, 4);
					}
					return "(web) - <a href=\"{$url}\">{$name}</a><br/>";
				default:
					// Unknown types or binary files
					return "[Unknown Type: $type] " . $name . "<br/>";

				}
			}
		}

		/**
		 * @brief Checks if the given host matches the current server's host.
		 *
		 * @param string $host The host string to check.
		 * @return bool True if the host matches, false otherwise.
		 */
		private function isThisHost($host){
			$checkHost = trim(strtolower($host));
			$thisHost = trim(strtolower($_SERVER['HTTP_HOST'] ?? ''));
			return $checkHost == $thisHost;
		}

		/**
		 * @brief Streams the converted HTML output to the browser.
		 *
		 * Iterates through the Gophermap content, converting each line to HTML
		 * and echoing it to the output buffer in chunks to manage memory
		 * efficiently, especially for large files. The output is wrapped
		 * in a <pre class="gopher"> block.
		 *
		 * @param int $buffer_size The size of the buffer (in bytes) before flushing output.
		 */
		public function stream() : void {
			$outputBuffer = $this->getIndentation() . "<pre class=\"gopher\">\n";
			foreach ($this as $line){
				$outputBuffer .= $line;
				if (strlen($outputBuffer) >= Config::$advanced->bufferSize){
					echo $outputBuffer;
					$outputBuffer = '';
					flush();
				}
			}
			if ($outputBuffer !== '') {
				echo $outputBuffer;
			}
			echo $this->getIndentation() . "</pre>\n";
			flush();
		}

		public function getTitle(string $fallback=null) : string {
			return empty($this->title) ? $fallback : $this->title;
		}

		public function getDescription(string $fallback=null) : string {
			return empty($this->description) ? $fallback : $this->description;
		}
	}
