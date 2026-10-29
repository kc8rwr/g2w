<?php
declare(strict_types=1);

/**
 * A backend-neutral drawing: a canvas plus a list of filled shapes.
 * Coordinates are pixels. Rects are inclusive [x0, y0, x1, y1] (GD/ImageMagick convention).
 * Colours are [r, g, b]; opacity runs from 0.0 (invisible) to 1.0 (solid).
 */
final class Scene
{
	/** @var list<array{0:string,1:array,2:array{int,int,int},3:float}> */
	public array $shapes = [];

	/** @param array{int,int,int}|null $bg  null = transparent background */
	public function __construct(public int $w, public int $h, public ?array $bg = null)
	{
	}

	public function rect(int $x0, int $y0, int $x1, int $y1, array $rgb, float $opacity = 1.0): static
	{
		$this->shapes[] = ['rect', [$x0, $y0, $x1, $y1], $rgb, $opacity];
		return $this;
	}

	/** @param list<array{float,float}> $points */
	public function polygon(array $points, array $rgb, float $opacity = 1.0): static
	{
		$this->shapes[] = ['poly', $points, $rgb, $opacity];
		return $this;
	}

	public function circle(float $cx, float $cy, float $r, array $rgb, float $opacity = 1.0): static
	{
		$this->shapes[] = ['circle', [$cx, $cy, $r], $rgb, $opacity];
		return $this;
	}

	/** Colour as a CSS/ImageMagick string: rgb(...) when solid, rgba(...) otherwise. */
	public static function color(array $rgb, float $opacity = 1.0): string
	{
		return $opacity >= 1.0
			? vsprintf('rgb(%d,%d,%d)', $rgb)
			: sprintf('rgba(%d,%d,%d,%s)', $rgb[0], $rgb[1], $rgb[2], self::num($opacity));
	}

	/** Locale-proof number formatting with at most 2 decimals. */
	public static function num(float $n): string
	{
		return rtrim(rtrim(sprintf('%.2F', $n), '0'), '.');
	}

}

interface Raster
{
	public static function available(): bool;
	public function name(): string;
	/** $format is 'png' or 'gif'. */
	public function supports(string $format): bool;
	public function render(Scene $scene, string $format): string;
}

/**
 * The way in to everything in this file: other code should only name this class
 * (plus Raster as a type), so an autoloader that maps class names to files finds it.
 *
 * Backends are known by key: 'gd', 'imagick', 'cli-magick', 'cli-convert', 'cli-gm'.
 */
final class RasterFactory
{
	/** Backends with a class of their own; the CLI programs come after these. */
	private const CLASSES = [
		'gd' => ['GD', GdRaster::class],
		'imagick' => ['Imagick', ImagickRaster::class],
	];

	/** @var array<string, array{string, Raster|null, string|null}|null> */
	private static array $info = [];

	/** @var array<string, Raster|null> */
	private static array $detected = [];

	/** A blank drawing for render(): $bg null = transparent background. */
	public static function scene(int $w, int $h, ?array $bg = null): Scene
	{
		return new Scene($w, $h, $bg);
	}

	/** @return list<string>  every backend key, in preference order */
	public static function keys(): array
	{
		return [...array_keys(self::CLASSES), ...array_map(fn($p) => "cli-$p", CliRaster::programs())];
	}

	/**
	 * One backend as [label, backend or null if it can't be used here, the reason it can't].
	 * Null for an unknown key.
	 *
	 * @return array{string, Raster|null, string|null}|null
	 */
	public static function info(string $key): ?array
	{
		if (!array_key_exists($key, self::$info)) {
			$program = str_starts_with($key, 'cli-') ? substr($key, 4) : null;
			if (isset(self::CLASSES[$key])) {
				[$label, $class] = self::CLASSES[$key];
				$problem = $class::problem();
				self::$info[$key] = [$label, $problem === null ? new $class() : null, $problem];
			} elseif ($program !== null && in_array($program, CliRaster::programs(), true)) {
				$label = $program === 'gm' ? 'gm convert (GraphicsMagick)' : "$program (ImageMagick)";
				self::$info[$key] = [$label, CliRaster::find($program), CliRaster::problem($program)];
			} else {
				self::$info[$key] = null;
			}
		}
		return self::$info[$key];
	}

	/** @return array<string, array{string, Raster|null, string|null}>  info() for every backend, usable or not */
	public static function all(): array
	{
		$all = [];
		foreach (self::keys() as $key) {
			$all[$key] = self::info($key);
		}
		return $all;
	}

	/** A backend by key, or null if it's unknown or can't be used here. */
	public static function get(string $key): ?Raster
	{
		return self::info($key)[1] ?? null;
	}

	/**
	 * An image file's size in pixels as [width, height], or null if it can't be read or nothing here can measure it.
	 * Tries PHP's own getimagesize() (no GD needed), then Imagick, then the ImageMagick/GraphicsMagick programs.
	 * Only the first frame of an animation is measured.
	 *
	 * @return array{int, int}|null
	 */
	public static function imageSize(string $path): ?array
	{
		$size = null;
		if (is_file($path) && is_readable($path)) {
			if (function_exists('getimagesize')) {
				$info = @getimagesize($path);
				if ($info !== false && $info[0] > 0 && $info[1] > 0) {
					$size = [$info[0], $info[1]];
				}
			}
			if ($size === null && ImagickRaster::available()) {
				$size = ImagickRaster::imageSize($path);
			}
			if ($size === null) {
				$size = CliRaster::find()?->imageSize($path);
			}
		}
		return $size;
	}

	/**
	 * The first usable backend that can write $format ('png' or 'gif'),
	 * or null if nothing here can. Stops at the first hit, so later backends aren't probed.
	 */
	public static function detect(string $format = 'png'): ?Raster
	{
		if (!array_key_exists($format, self::$detected)) {
			self::$detected[$format] = null;
			foreach (self::keys() as $key) {
				$r = self::get($key);
				if ($r !== null && $r->supports($format)) {
					self::$detected[$format] = $r;
					break;
				}
			}
		}
		return self::$detected[$format];
	}
}

final class GdRaster implements Raster
{
	/** GIF has no alpha channel, so transparent areas are painted this and marked transparent. */
	private const KEY = [255, 0, 255];

	public static function available(): bool
	{
		return self::problem() === null;
	}

	/** Why GD can't be used here, or null if it can. */
	public static function problem(): ?string
	{
		return function_exists('imagecreatetruecolor') ? null : 'GD extension not loaded';
	}

	public function name(): string
	{
		return 'gd';
	}

	public function supports(string $format): bool
	{
		return match ($format) {
			'png' => (bool) (imagetypes() & IMG_PNG),
			'gif' => (bool) (imagetypes() & IMG_GIF),
			default => false,
		};
	}

	public function render(Scene $s, string $format): string
	{
		$gif = $format === 'gif';
		$im = imagecreatetruecolor($s->w, $s->h);
		imagealphablending($im, false);
		if ($s->bg !== null) {
			imagefill($im, 0, 0, imagecolorallocate($im, $s->bg[0], $s->bg[1], $s->bg[2]));
		} elseif ($gif) {
			imagefill($im, 0, 0, imagecolorallocate($im, ...self::KEY));
		} else {
			imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
			imagesavealpha($im, true);
		}
		imagealphablending($im, true);

		foreach ($s->shapes as [$type, $g, $rgb, $op]) {
			// GD alpha runs backwards: 0 = opaque, 127 = fully transparent.
			$c = imagecolorallocatealpha($im, $rgb[0], $rgb[1], $rgb[2], (int) round(127 * (1 - $op)));
			switch ($type) {
				case 'rect':
					imagefilledrectangle($im, $g[0], $g[1], $g[2], $g[3], $c);
					break;
				case 'poly':
					$flat = [];
					foreach ($g as [$x, $y]) {
						$flat[] = (int) round($x);
						$flat[] = (int) round($y);
					}
					imagefilledpolygon($im, $flat, $c);
					break;
				case 'circle':
					$d = (int) round(2 * $g[2]);
					imagefilledellipse($im, (int) round($g[0]), (int) round($g[1]), $d, $d, $c);
					break;
			}
		}

		ob_start();
		if ($gif) {
			imagetruecolortopalette($im, false, 256);
			if ($s->bg === null) {
				imagecolortransparent($im, imagecolorclosest($im, ...self::KEY));
			}
			imagegif($im);
		} else {
			imagepng($im, null, 9);
		}
		return ob_get_clean();
	}
}

final class ImagickRaster implements Raster
{
	public static function available(): bool
	{
		return self::problem() === null;
	}

	/** Why Imagick can't be used here, or null if it can. */
	public static function problem(): ?string
	{
		return extension_loaded('imagick') ? null : 'imagick extension not loaded';
	}

	public function name(): string
	{
		return 'imagick';
	}

	public function supports(string $format): bool
	{
		return \Imagick::queryFormats(strtoupper($format)) !== [];
	}

	/** @return array{int, int}|null  see RasterFactory::imageSize() */
	public static function imageSize(string $path): ?array
	{
		$size = null;
		try {
			$im = new \Imagick();
			$im->pingImage($path . '[0]'); // reads the header only, not the pixels
			if ($im->getImageWidth() > 0 && $im->getImageHeight() > 0) {
				$size = [$im->getImageWidth(), $im->getImageHeight()];
			}
		} catch (\ImagickException) {
		}
		return $size;
	}

	public function render(Scene $s, string $format): string
	{
		$im = new \Imagick();
		$im->newImage($s->w, $s->h, new \ImagickPixel($s->bg !== null ? Scene::color($s->bg) : 'none'));
		$d = new \ImagickDraw();
		$d->setStrokeAntialias(false);
		foreach ($s->shapes as [$type, $g, $rgb, $op]) {
			$d->setFillColor(new \ImagickPixel(Scene::color($rgb, $op)));
			switch ($type) {
				case 'rect':
					$d->rectangle($g[0], $g[1], $g[2], $g[3]);
					break;
				case 'poly':
					$d->polygon(array_map(fn($p) => ['x' => $p[0], 'y' => $p[1]], $g));
					break;
				case 'circle':
					$d->circle($g[0], $g[1], $g[0] + $g[2], $g[1]);
					break;
			}
		}
		$im->drawImage($d);
		$im->setImageFormat($format === 'gif' ? 'gif' : 'png32');
		return $im->getImageBlob();
	}
}

/** Shells out to ImageMagick 7, ImageMagick 6 or GraphicsMagick. No shell involved. */
final class CliRaster implements Raster
{
	/** Candidate command prefixes, best first. */
	private const CANDIDATES = [['magick'], ['convert'], ['gm', 'convert']];

	/** @var array<string, self>|null  every working program, keyed by name ('magick', 'convert', 'gm') */
	private static ?array $all = null;

	/** @param list<string> $cmd */
	private function __construct(private array $cmd)
	{
	}

	public static function available(): bool
	{
		return self::find() !== null;
	}

	public function name(): string
	{
		return 'cli:' . implode(' ', $this->cmd);
	}

	public function supports(string $format): bool
	{
		// PNG and GIF are built into ImageMagick and GraphicsMagick.
		return in_array($format, ['png', 'gif'], true);
	}

	/**
	 * The best available program, or a specific one ('magick', 'convert' or 'gm').
	 * Returns null if it isn't installed.
	 */
	public static function find(?string $program = null): ?self
	{
		$all = self::findAll();
		if ($program === null) {
			return $all[array_key_first($all)] ?? null;
		}
		return $all[$program] ?? null;
	}

	/** @return list<string>  every program this backend knows how to use, best first */
	public static function programs(): array
	{
		return array_column(self::CANDIDATES, 0);
	}

	/** Why a program ('magick', 'convert' or 'gm') can't be used here, or null if it can. */
	public static function problem(string $program): ?string
	{
		if (isset(self::findAll()[$program])) {
			return null;
		}
		if (!function_exists('proc_open')) {
			return 'proc_open() is disabled in PHP';
		}
		if (self::which($program) === null) {
			return "$program not found in PATH";
		}
		return "$program is not ImageMagick or GraphicsMagick";
	}

	/** @return array<string, self>  every working program, best first */
	public static function findAll(): array
	{
		if (self::$all !== null) {
			return self::$all;
		}
		self::$all = [];
		if (!function_exists('proc_open')) {
			return self::$all;
		}
		foreach (self::CANDIDATES as $cand) {
			$bin = self::which($cand[0]);
			if ($bin === null) {
				continue;
			}
			// `convert` on its own could be something else entirely; make sure it's ImageMagick.
			[$out] = self::run([$bin, ...array_slice($cand, 1), '-version']);
			if (preg_match('/ImageMagick|GraphicsMagick/', $out)) {
				self::$all[$cand[0]] = new self([$bin, ...array_slice($cand, 1)]);
			}
		}
		return self::$all;
	}

	private static function which(string $name): ?string
	{
		foreach (explode(PATH_SEPARATOR, getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin') as $dir) {
			$p = rtrim($dir, '/') . '/' . $name;
			if (is_file($p) && is_executable($p)) {
				return $p;
			}
		}
		return null;
	}

	/** @return array{string,string,int} stdout, stderr, exit code */
	private static function run(array $argv): array
	{
		$p = proc_open($argv, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
		if (!is_resource($p)) {
			return ['', 'proc_open failed', -1];
		}
		$out = stream_get_contents($pipes[1]);
		$err = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		return [$out, $err, proc_close($p)];
	}

	/** @return array{int, int}|null  see RasterFactory::imageSize() */
	public function imageSize(string $path): ?array
	{
		$size = null;
		// GraphicsMagick's convert has no info: output, its identify does the same job
		$argv = basename($this->cmd[0]) === 'gm'
			? [$this->cmd[0], 'identify', '-format', '%w %h\n', $path . '[0]']
			: [...$this->cmd, $path . '[0]', '-format', '%w %h\n', 'info:'];
		[$out, , $code] = self::run($argv);
		if ($code === 0 && preg_match('/^(\d+) (\d+)/', $out, $m) && $m[1] > 0 && $m[2] > 0) {
			$size = [(int) $m[1], (int) $m[2]];
		}
		return $size;
	}

	/**
	 * GraphicsMagick misreads the alpha in rgba(), and its #RRGGBBAA means transparency
	 * (00 = solid) where ImageMagick's means opacity, so it gets its own colour format.
	 */
	private function color(array $rgb, float $opacity = 1.0): string
	{
		if ($opacity >= 1.0 || basename($this->cmd[0]) !== 'gm') {
			return Scene::color($rgb, $opacity);
		}
		return sprintf('#%02X%02X%02X%02X', $rgb[0], $rgb[1], $rgb[2], (int) round(255 * (1 - $opacity)));
	}

	public function render(Scene $s, string $format): string
	{
		$argv = [...$this->cmd, '-size', "{$s->w}x{$s->h}",
			'xc:' . ($s->bg !== null ? Scene::color($s->bg) : 'none'), '+antialias'];
		foreach ($s->shapes as [$type, $g, $rgb, $op]) {
			$draw = match ($type) {
				'rect' => "rectangle $g[0],$g[1] $g[2],$g[3]",
				'poly' => 'polygon ' . implode(' ', array_map(fn($p) => Scene::num($p[0]) . ',' . Scene::num($p[1]), $g)),
				'circle' => sprintf('circle %s,%s %s,%s',
					Scene::num($g[0]), Scene::num($g[1]), Scene::num($g[0] + $g[2]), Scene::num($g[1])),
			};
			array_push($argv, '-fill', $this->color($rgb, $op), '-draw', $draw);
		}
		$gif = $format === 'gif';
		$argv[] = ($gif ? 'gif' : 'png32') . ':-';
		[$out, $err, $code] = self::run($argv);
		if ($code !== 0 || !str_starts_with($out, $gif ? 'GIF8' : "\x89PNG")) {
			throw new \RuntimeException("{$this->name()} failed: " . trim($err));
		}
		return $out;
	}
}
