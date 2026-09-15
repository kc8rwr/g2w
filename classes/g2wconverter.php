<?php

	interface G2WConverter extends Iterator {

		public function __construct(string $input, bool $isPath, int $indentLevel, string $title = null, string $description = null);

		public function getTitle(string $fallback = null) : string;

		public function getDescription(string $fallback = null) : string;

		public function stream() : void;
	}



  
