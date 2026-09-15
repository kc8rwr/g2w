<?php

	/**
	 * @brief Converts plain text content to HTML, line by line or from a file.
	 *
	 * This class implements the Iterator interface to allow for streaming
	 * conversion of text, which is particularly useful for large files.
	 * It handles line ending normalization, converts regions of multiple
	 * spaces to non-breaking spaces, and escapes HTML entities.
	 */
	class TextToHTML implements Iterator {

		private $textLines = null; ///< Array of text lines if input is a string.
		private $path = null; ///< Path to the text file.
		private $index = 0; ///< Current line index for iteration.
		private $fileHandle = null; ///< File handle for reading from a path.
		private $currentHtmlLine = null; ///< The currently processed HTML line.

		private $indentLevel = 0; ///< Current indentation level.
		private $title = null; ///< The title for the header title tag
		private $description = null; ///< The description for the meta tag
		
		/**
		 * @brief Constructor for TextToHTML.
		 *
		 * Initializes the converter with either a text string or a file path.
		 * Normalizes line endings upon initialization for string input.
		 *
		 * @param string $input The text content string or file path.
		 * @param bool $is_path If true, $input is treated as a file path; otherwise, as a string.
		 * @param int $indentLevel The starting indentation level for generated HTML.
		 * @throws InvalidArgumentException If $is_path is true and $input is empty.
		 * @throws RuntimeException If $is_path is true and the file specified by $input is not found or inaccessible.
		 */
		function __construct(
			string $input,
			bool $is_path = false,
			int $indentLevel = 2,
			string $title = null,
			string $description = null,
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
				// Normalize line endings for string input
				$input = str_replace(["\r\n", "\r"], "\n", $input);
				$this->textLines = explode("\n", $input);
			}

			$this->indentLevel = $indentLevel;
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
		 * @brief Reads the next raw text line and converts it to HTML.
		 *
		 * This private method fetches the next line from either the internal array
		 * or the file handle, normalizes line endings, processes it through
		 * textLineToHtml(), and stores the result in $currentHtmlLine.
		 */
		private function readNextLine() : void {
			$this->currentHtmlLine = null;
			$rawLine = false;

			// fetch the raw data from whichever source we are using
			if (!is_null($this->textLines)){
				if (array_key_exists($this->index, $this->textLines)){
					$rawLine = $this->textLines[$this->index];
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
				// Normalize line endings for file content (fgets might return \r\n or \r)
				$rawLine = str_replace(["\r\n", "\r"], "\n", $rawLine);
				// Process the content, then add the HTML line break
				$this->currentHtmlLine = $this->getIndentation() . $this->textLineToHtml(rtrim($rawLine, "\n")) . "<br/>\n";
			}
		}

		/**
		 * @brief Generates the current indentation string based on the current indent level.
		 *
		 * @return string The indentation string (e.g., tabs or spaces).
		 */
		private function getIndentation() : string {
			return str_repeat("\t", max(0, $this->indentLevel));
		}

		/**
		 * @brief Converts a single text line into its corresponding HTML representation.
		 *
		 * This method first HTML-escapes the content, then replaces regions of
		 * multiple spaces (greater than 1 in width) with HTML non-breaking spaces.
		 *
		 * @param string $line The raw text line to convert.
		 * @return string The HTML representation of the line.
		 */
		private function textLineToHtml(string $line) : string {
			$output = htmlspecialchars($line);

			// Convert regions of space characters greater than 1 in width to &nbsp;
			// A single space remains a single space.
			$output = preg_replace_callback('/( +)/', function($matches) {
				$spaces = $matches[1];
				if (strlen($spaces) > 1) {
					return str_repeat('&nbsp;', strlen($spaces));
				}
				return $spaces; // Leave single spaces as is
			}, $output);

			return $output;
		}

		/**
		 * @brief Extracts the first non-empty line as a potential title.
		 *
		 * @param string|null $fallback An optional fallback title if no title is found.
		 * @return string The extracted (and HTML-escaped) title, or the fallback.
		 */
		function getTitle($fallback = null) : string {
			if (null == $this->title){
				$title = $fallback;
				$contentSource = null;
				
				if (!is_null($this->textLines)) {
					$contentSource = $this->textLines;
				} elseif (!empty($this->path) && file_exists($this->path)) {
					try {
						$contentSource = new SplFileObject($this->path);
					} catch (Exception) {
						$contentSource = null;
					}
				}
				
				if ($contentSource) {
					foreach ($contentSource as $line) {
						// Normalize line endings before processing
						$line = str_replace(["\r\n", "\r"], "\n", $line);
						$trimmedLine = trim($line);
						if (!empty($trimmedLine)) {
							// Apply the same conversion logic for spaces and HTML entities
							$title = $this->textLineToHtml($trimmedLine);
							break;
						}
					}
				}
				$this->title = $title;
			}
			return empty($this->title ? $fallback : $this->title);
		}

		/**
		 * @brief Extracts a description from the text content.
		 *
		 * It attempts to find suitable descriptive text from the beginning of the file,
		 * skipping empty lines, and truncates it to a maximum number of characters.
		 *
		 * @param int $maxChars The maximum number of characters for the description.
		 * @return string The extracted (and HTML-escaped) description.
		 */
		function getDescription($fallback = null) : string {
			if (is_null($this->description)){
				$description = '';
				$contentSource = null;
				
				if (!is_null($this->textLines)) {
					$contentSource = $this->textLines;
				} elseif (!empty($this->path) && file_exists($this->path)) {
					try {
						$contentSource = new SplFileObject($this->path);
					} catch (Exception) {
						$contentSource = null;
					}
				}
				
				if ($contentSource) {
					$currentLength = 0;
					foreach ($contentSource as $line) {
						// Normalize line endings before processing
						$line = str_replace(["\r\n", "\r"], "\n", $line);
						$trimmedLine = trim($line);
						
						if (!empty($trimmedLine)) {
							// Apply the conversion logic for spaces and HTML entities
							$processedLine = $this->textLineToHtml($trimmedLine);
							$lineLength = mb_strlen($processedLine);
							
							// Check if adding this line (plus a potential space separator) exceeds maxChars
							$potentialNewLength = $currentLength + ($currentLength > 0 ? 1 : 0) + $lineLength; // +1 for space if not first line
							
							if ($potentialNewLength <= Config::$seo->maxDescriptionChars) {
								if (!empty($description)) {
									$description .= ' ';
								}
								$description .= $processedLine;
								$currentLength = $potentialNewLength;
							} else {
								// Current line cannot fit entirely, truncate it if possible
								$remainingChars = Config::$seo->maxDescriptionChars - $currentLength - ($currentLength > 0 ? 1 : 0); // Remaining space for content of current line
								if ($remainingChars > 0) {
									if (!empty($description)) {
										$description .= ' ';
									}
									$description .= mb_substr($processedLine, 0, $remainingChars);
								}
								break; // Description limit reached
							}
						}
					}
				}
				$this->description = trim($description);
			}
			// Ensure final description is trimmed
			return empty($this->description) ? $fallback : $this->description;
		}

		/**
		 * @brief Streams the converted HTML output to the browser.
		 *
		 * Iterates through the text content, converting each line to HTML
		 * and echoing it to the output buffer in chunks to manage memory
		 * efficiently, especially for large files. The output is wrapped
		 * in a <pre class="text"> block.
		 *
		 * @param int $buffer_size The size of the buffer (in bytes) before flushing output.
		 */
		public function stream() {
			$outputBuffer = $this->getIndentation() . "<pre class=\"text\">\n";
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
	}
	
