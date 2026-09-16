<?php
	//"<ul class=\"directory-grid\">\n";
	class folderToHTML implements G2WConverter {
		private string $path; ///< Path to the folder
		private int $index = 0; ///< Index of current file for iterator
		private array $files; //< Array of file names
		private $currentHtmlLine; //< The currently processed filepath as html
		private $title = null; //< The title
		private $description = null; //< The meta description
		
		private int $indentLevel = 0; ///< The indentation level for the whole folder
		
		/**
		 * @brief Constructor for GenerateFolderHTML.
		 *
		 * Initializes the converter with the path to the directory and other configuration details.
		 *
		 * @param string $input The absolute path to the directory or a string listing files, comma separated
		 * @param bool $is_path Treat $input as a path to a folder if true otherwise as a list of files
		 * @param int $indentLevel The base number of indents to apply to the generated HTML.
		 * @throws InvalidArgumentException If $path is empty or not a valid directory.
		 */
		public function __construct(
			string $input,
			bool $is_path = false,
			int $indentLevel = 2,
			$title = null,
			$description = null,
		) {
			$files = null;
			if ($is_path){
				if (empty($input)){
					throw new InvalidArgumentException('File path cannot be empty.');
				}
				if (!(file_exists($input) && is_dir($input))){
					throw new RuntimeException("Directory not found or inaccessible {$input}");
				}
				$renum = false;
				$this->path = trim($input);
				$files = scandir($this->path);
				for ($i = count($files) - 1; $i > -1; $i--){
					$file = $files[$i];
					if (str_starts_with($file, '.')){
						if (0 !== strcasecmp($file, '.gophermap')
							&& ('..' != $file || '' == Config::$paths->relativeUri )){
								unset($files[$i]);
								$renum = true;
						}
					}
				}
				if ($renum){
					$files = array_values($files);
				}
			} else {
				if (null == $input) {
					$input = '';
				}
				// Normalize line endings for string input
				$input = str_replace(["\r\n", "\r"], "\n", $input);
				$files = explode("\n", $input);
				array_walk($files, function(&$file) {
					$file = trim($file);
				});
			}
			$this->files = $files;
			$this->indentLevel = max(0, $indentLevel);
			$this->title = $title;
			$this->description = $description;
		}

		public function getTitle(string $fallback = null) : string {
			if (is_null($this->title)){
				$this->title = "Directory Listing " . Config::$paths->relativeUri;
			}
			return empty($this->title) ? $fallback : $this->title;
		}

		public function getDescription($fallback = null) : string {
			if (is_null($this->description)){
				$this->description = "Directory Listing " . Config::$paths->relativeUri;
			}
			return empty($this->description) ? $fallback : $this->title;
		}

		public function stream() : void {
			$outputBuffer = str_repeat("\t", $this->indentLevel) . "<ul class=\"directory-grid\">\n";
			foreach ($this as $line){
				$outputBuffer .= "\t" . $line;
				if (strlen($outputBuffer) >= Config::$advanced->bufferSize){
					echo $outputBuffer;
					$outputBuffer = '';
					flush();
				}
			}
			if ($outputBuffer !== '') {
				echo $outputBuffer;
			}
			echo str_repeat("\t", $this->indentLevel) . "</ul>\n";
			flush();
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

		private function readNextLine() : void {
			$line = null;
			if ((!empty($this->files)) && $this->index < count($this->files)){
				$line .= str_repeat("\t", $this->indentLevel);
				$line .= $this->fileNameToHtml($this->files[$this->index]);
			}
			$this->currentHtmlLine = $line;
		}

		private function fileNameToHtml(string $file): string {
			$output = '';
			$lc_file = strtolower($file);
			$filePath = $this->path . DIRECTORY_SEPARATOR . $file;
			if (is_dir($filePath)) {
				$ext = 'dir';
			} else {
				$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION)) ?: $file; // Use filename as ext if no extension
			}

			// Initialize label and type for the current file/directory
			$label = '';
			$type = '';

			switch (strtolower($ext)) {
				// Config / Data
			case 'conf':
			case 'cnf':
				$label = 'Config file';
				$ext = 'conf'; // Canonical ext for icon lookup
				$type = 'config';
				break;
			case 'ini':
				$label = 'INI file';
				$type = 'config';
				break;
			case 'json':
				$label = 'JSON file';
				$type = 'config';
				break;
			case 'toml':
				$label = 'Toml file';
				$type = 'config';
				break;
			case 'sys':
				$is_binary = $this->isBinaryFile($filePath);
				$label = $is_binary ? 'Sys binary' : 'Sys config';
				$type = $is_binary ? 'executable' : 'config';
				break;
			case 'yaml':
			case 'yml':
				$label = 'Yaml file';
				$type = 'config';
				$ext = 'yaml';
				break;
				
				// Markup
			case 'adoc':
			case 'asciidoc':
			case 'ad':
				$label = 'AsciiDoc document';
				$ext = 'adoc';
				break;
			case 'creole':
				$label = 'Creole markup document';
				break;
			case 'gmi':
			case 'gemini':
				$label = 'Gemtext page';
				$ext = 'gmi';
				$file .= '.' . Config::$behaviors->htmlExt;
				break;
			case 'html':
			case 'htm':
				$label = 'HTML document';
				$ext = 'html';
				break;
			case 'md':
			case 'mkd':
				$label = 'Markdown document';
				$ext = 'md';
				break;
			case 'rst':
				$label = 'reStructuredText document';
				break;
			case 'textile':
				$label = 'Textile document';
				break;
			case 'wiki':
				$label = 'WikiText document';
				break;
				
				// Documents & Pages
			case 'csv':
				$label = 'Comma separated values';
				break;
			case 'dia':
				$label = 'Dia diagram';
				break;
			case 'doc':
			case 'docx':
				$label = 'MS-Word document';
				$ext = 'doc';
				break;
			case 'gophermap':
			case 'gph' :
				$label = 'Gopher directory map';
				$ext = 'gophermap';
				break;
			case 'ods':
				$label = 'LibreOffice spreadsheet';
				break;
			case 'odt':
				$label = 'LibreOffice document';
				break;
			case 'ppt':
			case 'pptx':
				$label = 'MS-PowerPoint presentation';
				$ext = 'ppt';
				break;
			case 'pub':
				$label = 'MS-Publisher document';
				break;
			case 'pm4':
			case 'pm5':
			case 'pm6':
			case 'pm65':
			case 'pmd' :
				$label = 'PageMaker document';
				$ext = 'pmd'; // Use a canonical name for icon
				break;
			case 'rtf':
				$label = 'Rich text document';
				break;
			case 'tsv':
				$label = 'Tab separated values';
				break;
			case 'txt':
				$label = 'Plain text file';
				break;
			case 'pdf':
				$label = 'PDF document';
				break;
			case 'sxw':
			case 'sxc':
				$label = 'Star Office document';
				$ext = 'sxw';
				break;
			case 'vsd':
			case 'vsdx':
				$label = 'Microsoft Visio diagram';
				$ext = 'vsd';
				break;
			case 'wk1':
			case '123':
				$label = 'Lotus 1-2-3 spreadsheet';
				$ext = 'wk1';
				break;
			case 'wks':
			case 'wps':
				$label = 'Microsoft Works document';
				$ext = 'wks';
				break;
			case 'wpd':
				$label = 'WordPerfect document';
				break;
			case 'xls':
			case 'xlsx':
				$label = 'MS-Excel Spreadsheet';
				$ext = 'xls';
				break;
				
				// Compressed & Archive
			case '7z':
				$label = '7-Zip archive';
				break;
			case 'apk':
				$apk_type = $this->getAPKType($filePath);
				switch ($apk_type){
				case 'android':
					$label = 'Android apk package';
					$ext = 'apk';
					break;
				case 'alpine':
					$label = 'Alpine Linux apk package';
					$ext = 'alpine_apk';
					break;
				default:
					$label = 'APK package';
					$ext = 'apk';
					break;
				}
				break;
			case 'bin':
				$label = 'Binary image';
				break;
			case 'bz':
			case 'bz2':
				if (str_ends_with($lc_file, 'tar.bz') || str_ends_with($lc_file, 'tar.bz2')){
					$label = 'Bzipped Tar Archive';
					$ext = 'tbz';
				} else {
					$label = 'Bzip compressed file';
					$ext = 'bz'; // Canonical for icon
				}
				break;
			case 'gz':
				if (str_ends_with($lc_file,'tar.gz')){
					$ext = 'tgz';
					$label = 'Gzipped Tar Archive';
				} else {
					$label = 'Gzip compressed file';
					$ext = 'gz'; // Canonical for icon
				}
				break;
			case 'iso':
				$label = 'Optical disc image';
				break;
			case 'hqx':
				$label = 'Macintosh BinHex archive';
				break;
			case 'rar':
				$label = 'RAR archive';
				break;
			case 'sit':
				$label = 'StuffIt archive';
				break;
			case 'tar':
				$label = 'Tar archive';
				break;
			case 'tbz':
			case 'tbz2':
				$label = 'BZipped Tar Archive';
				$ext = 'tbz';
				break;
			case 'tgz':
				$label = 'GZipped Tar Archive';
				break;
			case 'xz':
				$label = 'XZ compressed file';
				break;
			case 'zip':
				$label = 'ZIP compressed archive';
				break;
			case 'zst':
				if (str_ends_with($lc_file, '.pkg.tar.zst')){
					$label = 'Arch package';
					$ext = 'arch_zst';
				} else {
					$label = 'ZStandard compressed file';
					$ext = 'zst'; // Canonical for icon
				}
				break;
				
				// Images
			case 'bmp':
				$label = 'Bitmap image';
				break;
			case 'gif':
				$label = 'GIF image';
				break;
			case 'jpg':
			case 'jpeg':
				$label = 'JPEG image';
				$ext = 'jpg';
				break;
			case 'png':
				$label = 'PNG image';
				break;
				
				// Audio
			case 'aac':
				$label = 'AAC audio file';
				break;
			case 'aiff':
				$label = 'AIFF audio file';
				break;
			case 'au':
				$label = 'Sun audio file';
				break;
			case 'flac':
				$label = 'Flac audio file';
				break;
			case 'it':
				$label = 'Impulse Tracker audio file';
				break;
			case 'm4a':
				$label = 'M4A audio file';
				break;
			case 'med':
				$label = 'OctaMED audio file';
				break;
			case 'mid':
			case 'midi':
			case 'rmi':
			case 'kar':
				$label = 'MIDI audio';
				$ext = 'midi';
				break;
			case 'mp3':
				$label = 'MP3 audio file';
				break;
			case 'mod':
				$label = 'Mod audio file';
				break;
			case 'ra':
				$label = 'Real audio file';
				break;
			case 's3m':
				$label = 'Scream Tracker audio file';
				break;
			case 'wav':
				$label = 'WAV audio file';
				break;
			case 'xm':
				$label = 'Fast Tracker 2 Ext Mod audio file';
				break;
				
				// Video
			case 'avi':
				$label = 'AVI video file';
				break;
			case 'flv':
				$label = 'FLV video file';
				break;
			case 'mkv':
				$label = 'Matroska video file';
				break;
			case 'mov':
				$label = 'QuickTime video file';
				$ext = 'mov';
				break;
			case 'mp4':
				$label = 'MP4 video file'; // Generic MP4 for both audio/video
				break;
			case 'rv':
			case 'rmvb':
				$label = 'Real Video';
				$ext = 'rv';
				break;
			case 'webm':
				$label = 'WebM video file';
				break;
			case 'wmv':
				$label = 'Windows Media video file';
				break;
				
				// Generic Media (some overlaps with specific audio/video, fine for generic icon)
			case 'ogg':
				$label = 'Ogg media file';
				break;
			case 'rm':
			case 'ram':
			case 'rp':
			case 'rt':
				$label = 'Real Media';
				$ext = 'rm';
				break;
				
				// Programming & Data
			case 'a68':
			case 'alg':
			case 'a':
			case 'w':
				$label = 'ALGOL source code';
				$ext = 'a68';
				break;
			case 'ada':
			case 'adb':
			case 'ads':
				$label = 'Ada source code';
				$ext = 'ada';
				break;
			case 'apl':
			case 'dyalog':
				$label = 'APL source code';
				$ext = 'apl'; // Canonical
				break;
			case 'asm':
			case 's':
				$label = 'Assembler source code';
				$ext = 'asm';
				break;
			case 'bas':
			case 'vb':
				$label = 'BASIC source code';
				$ext = 'bas';
				break;
			case 'bat':
				$label = 'Batch File';
				break;
			case 'c':
				$label = 'C source code';
				break;
			case 'c++':
			case 'cc':
			case 'cpp':
				$label = 'C++ source code';
				$ext = 'cpp';
				break;
			case 'cbl':
			case 'cob':
			case 'cobol':
				$ext = 'cbl';
				$label = 'COBOL source code';
				break;
			case 'class':
				$label = 'Java class file';
				break;
			case 'cs':
				$label = 'C# source code';
					break;
			case 'css':
				$label = 'CSS file';
				break;
			case 'erl':
				$label = 'Erlang source code';
				break;
			case 'f90':
			case 'f':
			case 'for':
			case 'f95':
			case 'f03':
			case 'f08':
			case 'ftn':
				$ext = 'for';
				$label = 'Fortran source code';
				break;
			case 'forth':
			case '4th':
				$label = 'Forth source code';
				$ext = 'forth';
				break;
			case 'go':
				$label = 'Golang source code';
				break;
			case 'h':
			case 'hrl': //Erlang headers
				$label = 'Header file';
				$ext = 'h';
				break;
			case 'hs':
			case 'lhs':
				$label = 'Haskell source code';
				$ext = 'hs';
				break;
			case 'ino':
				$label = 'Arduino source code';
				break;
			case 'java':
				$label = 'Java source code';
				break;
			case 'js':
				$label = 'Javascript source code';
				break;
			case 'kt':
			case 'kts':
				$label = 'Kotlin source code';
				$ext = 'kt';
				break;
			case 'lisp':
			case 'lsp':
			case 'cl':
			case 'l':
			case 'el':
				$label = 'List source code';
				$ext = 'lisp';
				break;
			case 'm':
				$label = 'Objective C source code';
				break;
			case 'ml':
			case 'sml':
				$label = 'ML source code';
				$ext = 'ml';
				break;
			case 'mm':
				$label = 'Objective C++ source code';
				break;
			case 'php':
			case 'php3':
			case 'php4':
			case 'php5':
			case 'php6':
			case 'php7':
			case 'php8':
				$label = 'PHP script';
				$ext = 'php'; // Canonical
				break;
			case 'p':
			case 'pas':
			case 'pp':
				$label = 'Pascal source code';
				$ext = 'pas';
				break;
			case 'pl':
				$plType = $this->getPLType($filePath);
				switch ($plType){
				case 'perl':
					$label = 'Perl source code';
					$ext = 'pl';
					break;
				case 'prolog':
					$label = 'Prolog source code';
					$ext = 'pro'; // Canonical
					break;
				default:
					$label = 'PL file';
					$ext = 'default'; // For unknown PL types
				}
				break;
			case 'py':
				$label = 'Python source code';
				break;
			case 'pli':
			case 'pl1':
				$label = 'PL/I source code';
				$ext = 'pli';
				break;
			case 'pro':
				$label = 'Prolog source code';
				break;
			case 'rb':
			case 'rbw':
				$label = 'Ruby source code';
				$ext = 'rb';
				break;
			case 'rs':
				$label = 'Rust source code';
				$ext = 'rs';
				break;
			case 'sim':
			case 'sml':
				$label = 'Simula source code';
				$ext = 'sim';
				break;
			case 'swift':
				$label = 'Swift source code';
				break;
			case 'sql':
			case 'mysql':
				$label = 'SQL script';
				$ext = 'sql';
				break;
			case 'tcl':
			case 'tk':
				$label = 'Tcl Script';
				$ext = 'tcl';
				break;
			case 'ts':
			case 'tsx':
				$label = 'TypeScript source code';
				$ext = 'ts';
				break;

				// CAD & 3D
			case '3dm':
				$label = 'Rhino 3D file';
				break;
			case '3mf':
				$label = '3d Manufacturing file';
				break;
			case 'dae':
				$label = 'Collada 3d object';
				break;
			case 'dwg':
			case 'dxf':
				$label = 'AutoCad file';
				$ext = 'dwg';
				break;
			case 'fcstd':
				$label = 'FreeCAD file';
				break;
			case 'gcode':
			case 'g':
				$label = 'G-Code file';
				$ext = 'gcode';
				break;
			case 'gltf':
			case 'glb':
				$label = 'GL Transmisson 3d object';
				$ext = 'gltf'; // Canonical
				break;
			case 'stl':
				$label = '3D model file';
				break;
			case 'scad':
				$label = 'OpenSCAD design';
				break;
			case 'sldprt':
				$label = 'SolidWorks file';
				break;
			case 'step':
			case 'stp':
				$label = 'STEP file';
				$ext = 'step';
				break;
				
				// EDA
			case 'brd':
				$label = 'Eagle PCB Layout';
				break;
			case 'cir':
			case 'sp':
				$label = 'SPICE Simulation';
				$ext = 'cir';
				break;
			case 'drl':
			case 'xnc':
				$label = 'Drill file';
				$ext = 'drl';
				break;
			case 'gbr':
			case 'pho':
				$label = 'Gerber file';
				$ext = 'gbr';
				break;
			case 'kicad_pcb':
				$label = 'KiCAD PCB Layout';
				break;
			case 'kicad_pro':
				$label = 'KiCAD Project';
				break;
			case 'kicad_sch':
				$label = 'KiCAD Schematic';
				break;
			case 'pcbdoc':
				$label = 'Altium PCB Layout';
				break;
			case 'sch':
				$sch_type = $this->identifySchType($filePath);
				switch ($sch_type){
				case 'eagle':
					$label = 'Eagle Schematic';
					$ext = 'eagle_sch';
					break;
				case 'geda':
					$label = 'gEDA Schematic';
					$ext = 'geda_sch';
					break;
				default:
					$label = 'SCH file';
					$ext = 'sch'; // Canonical
					break;
				}
				break;
			case 'schdoc':
				$label = 'Altium Schematic';
				break;
				
				// Navigation & Directories
			case 'up':
				$label = 'Parent directory';
				break;
			case 'dir':
				$label = 'Folder';
				break;
				
				// Executables
			case 'appimage':
				$label = 'AppImage';
				break;
			case 'com':
				$label = 'CP/M or MS-DOS executable';
				break;
			case 'exe':
				$label = 'MS-DOS or Windows executable';
				break;
			case 'sh':
				$label = 'Shell script';
				break;
			case 'so':
				$label = 'ELF executable or library';
				break;
			case 'hex':
				$label = 'Hex File';
				break;
				
				// Packages
			case 'deb':
				$label = 'Debian package';
				break;
			case 'dmg':
				$label = 'Mac OS dmg disk image';
				break;
			case 'ebuild':
				$label = 'Gentoo ebuild';
				break;
			case 'flatpak':
				$label = 'Flatpak package';
				break;
			case 'jar':
				$label = 'Java archive';
				break;
			case 'msi':
				$label = 'Windows installer';
				break;
			case 'rpm':
				$label = 'RedHat package';
				break;
			case 'snap':
				$label = 'Snap package';
				break;
			case 'whl':
				$label = 'Python wheel';
				break;
				
				//PIM Files
			case 'ics':
			case 'vcs':
				$label = 'Calendar file';
				$ext = 'ics';
				break;
			case 'ldif':
				$label = 'LDAP data';
				break;
			case 'pdb':
			case 'prc':
				$label = 'Palm Database file';
				$ext = 'pdb';
				break;
			case 'pst':
			case 'ost':
				$label = 'Outlook file';
				$ext = 'pst';
				break;
			case 'vcf':
				$label = 'vCard e-buisiness card file';
				break;
					
				// Assorted
			case 'obj':
				$obj_type = $this->identifyObjType($filePath);
				switch ($obj_type){
				case 'windows':
					$label = 'Windows object file';
					$ext = 'obj'; // Canonical
					break;
				case 'elf':
					$label = 'ELF object file';
					$ext = 'obj'; // Canonical
					break;
				case 'macos':
					$label = 'MacOS object file';
					$ext = 'obj'; // Canonical
					break;
				case 'wavefront':
					$label = 'Wavefront 3d object';
					$ext = 'wavefront';
					break;
				default:
					$label = 'Object file';
					$ext = 'obj'; // Canonical fallback
					break;
				}
				break;
				
				// Default Fallback
			default:
				if (str_starts_with(strtolower(ltrim($lc_file, '. ')), 'gophermap')){
					$label = 'Gopher directory map';
					$ext = 'gophermap';
				} else {
					$label = strtoupper($ext) . ' file';
					// Keep $ext as is for default cases if no specific icon handling
				}
				break;
			}
			
			$icon = $this->getIconUrl($ext);

			// Determine the URL for the link
			$linkUrl = ($file == '../') ? $file : urlencode($file);

			$output .= str_repeat("\t", $this->indentLevel) . "<li class=\"file-item\"><a href=\"{$linkUrl}\">&nbsp;&nbsp;";
			$output .= "<div class=\"file-icon\" role=\"img\" aria-hidden=\"true\">";
			if (empty($icon)){
				$output .= "<span class=\"icon-placeholder\">[{$ext}]</span>";
			} else {
				$output .= "<img src=\"{$icon}\" class=\"icon-img\" alt=\"\" aria-hidden=\"true\" />";
			}
			$output .= "</div>";
			$output .= "<span class=\"file-name\">{$label}<br/>{$file}</span>";
			$output .= "</a></li>\n";
			
			$output .= "</li>\n";
			return $output;
		}

		/**
		 * @brief Determines the type of APK file (Android or Alpine Linux).
		 * @param string $filePath The path to the APK file.
		 * @return string 'android', 'alpine', or 'unknown'.
		 */
		private function getAPKType(string $filePath): string {
			$handle = @fopen($filePath, 'rb');
			if (false !== $handle){
				try{
					$header = fread($handle, 4);
				} finally {
					fclose($handle);
				}
				if (false !== $header){
					// Check for Android APK (ZIP magic number)
					if (strpos($header, "PK\x03\x04") === 0) {
						return 'android';
					}
					// Check for Alpine APK (GZIP magic number)
					elseif (strpos($header, "\x1f\x8b") === 0) {
						return 'alpine';
					}
				}
			}
			return 'unknown';
		}

		/**
		 * @brief Checks if a given file is likely a binary file.
		 * @param string $filePath The path to the file.
		 * @return bool True if the file contains null bytes, false otherwise.
		 */
		private function isBinaryFile(string $filePath): bool {
			if (!is_file($filePath)) return false;

			$fh = @fopen($filePath, 'rb');
			if ($fh === false) return false;

			$data = fread($fh, 512); // Check the first 512 bytes
			fclose($fh);

			// Check if the data contains a null byte
			return strpos($data, "\0") !== false;
		}

		/**
		 * @brief Identifies the type of a Perl/Prolog-like file.
		 * @param string $filePath The path to the file.
		 * @return string 'perl', 'prolog', 'binary', or 'unknown'.
		 */
		private function getPlType(string $filePath): string {
			$handle = @fopen($filePath, 'r');
			if (!$handle) return 'unknown';

			$raw = fread($handle, 1024);
			fclose($handle);

			if (empty($raw)) return 'unknown';

			// Check for Null bytes first to catch binary files
			$nullCount = substr_count($raw, "\0");
			if ($nullCount > 10) return 'binary'; // Arbitrary threshold: binary files have many nulls

			// Detect Encoding
			$encoding = mb_detect_encoding($raw, ['UTF-8', 'UTF-16', 'ISO-8859-1', 'ASCII'], true);

			// Normalize to UTF-8 for Regex consistency
			$content = ($encoding && $encoding !== 'UTF-8')
				? mb_convert_encoding($raw, 'UTF-8', $encoding)
				: $raw;

			// Logic Checks
			if (preg_match('/^#!.*perl/i', $content)) return 'perl';
			if (preg_match('/[\$@%][a-zA-Z_]/', $content) || str_contains($content, 'my $')) return 'perl';

			if (preg_match('/[a-z0-9_]+\(.*\) *:-/i', $content) || preg_match('/^[a-z0-9_]+\(.*\)\./m', $content)) return 'prolog';

			return 'unknown';
		}

		/**
		 * @brief Identifies the type of an object file or 3D model.
		 * @param string $path The path to the file.
		 * @return string 'windows', 'elf', 'macos', 'wavefront', 'Invalid File', or 'unknown'.
		 */
		private function identifyObjType(string $path): string {
			if (!file_exists($path) || !is_readable($path)) {
				return "Invalid File";
			}

			$file = @fopen($path, 'rb');
			if ($file === false) {
				return "Invalid File";
			}

			try {
				$header = fread($file, 8);
			} finally {
				fclose($file);
			}

			$hex = bin2hex($header);

			if (false !== $hex){
				$hex = strtoupper($hex);

				if (str_starts_with($hex, '4C01') || str_starts_with($hex, '6486') || str_starts_with($hex, '8664')) {
					return "windows";
				}

				if (str_starts_with($hex, '7F454C46')) {
					return "elf";
				}

				if (str_starts_with($hex, 'FEEDFACE') || str_starts_with($hex, 'FEEDFACF')) {
					return "macos";
				}

				// For Wavefront .obj files, we need to read lines
				$file = @fopen($path, 'r');
				if ($file === false) {
					return "unknown"; // Could not open as text, might be binary
				}

				try {
					for ($i = 0; $i < 30; $i++) { // Check first 30 lines
						$line = fgets($file);
						if ($line === false) break;

						$trimmed = trim($line);

						if ($trimmed === '' || str_starts_with($trimmed, '#')) continue;

						if (preg_match('/^(v|f|vt|vn|mtllib|usemtl|o|g)\s/', $trimmed)) {
							return "wavefront";
						}
					}
				} finally {
					fclose($file);
				}
			}
			return "unknown";
		}

		/**
		 * @brief Identifies the type of a schematic file.
		 * @param string $path The path to the schematic file.
		 * @return string 'eagle', 'geda', 'Invalid file', or 'unknown'.
		 */
		private function identifySchType(string $path): string {
			$firstLine = null;
			$file = @fopen($path, 'r');
			if ($file === false){
				return 'Invalid file';
			}
			try
			{
				$firstLine = fgets($file);
			} finally {
				fclose($file);
			}

			if (false !== $firstLine){

				if (strpos($firstLine, '<?xml') !== false) {
					return 'eagle';
				}

				if (preg_match('/^v \d{8}/', $firstLine)) {
					return 'geda';
				}

			}
			return 'unknown';
		}

		/**
		 * @brief Gets the absolute file path for an icon based on its name and current theme.
		 * @param string $icon The base name of the icon (e.g., 'dir', 'html').
		 * @return string|null The absolute path to the icon file, or null if not found.
		 */
		public static function getIconPath(string $icon): ?string {
			$theme = Config::$aesthetics->theme ?? 'default';
			$file_path = null;

			// 1. Try theme-specific icon
			if (!empty($theme) && $theme !== 'default') {
				$potentialPath = Config::$paths->basePath . "/themes/{$theme}/icons/{$icon}.gif";
				if (file_exists($potentialPath)) {
					$file_path = $potentialPath;
				} else {
					// Fallback to theme's generic default icon
					$potentialDefaultPath = Config::$paths->basePath . "/themes/{$theme}/icons/default.gif";
					if (file_exists($potentialDefaultPath)) {
						$file_path = $potentialDefaultPath;
					}
				}
			}

			// 2. Fallback to default theme icon if not found in specific theme or if theme is 'default'
			if (empty($file_path)) {
				$potentialPath = Config::$paths->basePath . "/themes/default/icons/{$icon}.gif";
				if (file_exists($potentialPath)) {
					$file_path = $potentialPath;
				} else {
					// Fallback to default theme's generic default icon (should always exist)
					$potentialDefaultPath = Config::$paths->basePath . "/themes/default/icons/default.gif";
					if (file_exists($potentialDefaultPath)) {
						$file_path = $potentialDefaultPath;
					}
				}
			}
			return $file_path;
		}

		/**
		 * @brief Gets the URL for an icon, including cache busting.
		 * @param string $icon The base name of the icon (e.g., 'dir', 'html').
		 * @return string|null The URL to the icon, or null if icon path not found.
		 */
		private function getIconUrl(string $icon): ?string {
			$output = null;
			$file_path = static::getIconPath($icon);
			if (!empty($file_path)){
				$lastmod_suffix = '';
				if (Config::$lastmod > 0) { // Only add if configLastmod is provided
					$lastmod = filemtime($file_path);
					$lastmod = max($lastmod, Config::$lastmod);
					$lastmod_suffix = "?v={$lastmod}";
				}
				// Construct the URL relative to the base URL
				// Assuming g2w/icons/ is a virtual path handled by the web server
				$output = Config::$paths->baseUrl . "/g2w/icons/{$icon}.gif{$lastmod_suffix}";
			}
			return $output;
		}
	}
