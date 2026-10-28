<?php
declare(strict_types=1);

require_once __DIR__ . '/rasterfactory.php';

final class DomainArt
{
	public const TILE = 96;

	/** Background tile lightness, in percent: the base fill, and the range its shapes are drawn in. */
	public const BG_LIGHTNESS = 80;
	public const BG_SHAPE_LIGHTNESS = [62, 72];

	/** CSS/HTML named colours, for hueOf(). */
	private const NAMED_COLORS = [
		'aliceblue' => '#f0f8ff', 'antiquewhite' => '#faebd7', 'aqua' => '#00ffff',
		'aquamarine' => '#7fffd4', 'azure' => '#f0ffff', 'beige' => '#f5f5dc', 'bisque' => '#ffe4c4',
		'black' => '#000000', 'blanchedalmond' => '#ffebcd', 'blue' => '#0000ff',
		'blueviolet' => '#8a2be2', 'brown' => '#a52a2a', 'burlywood' => '#deb887',
		'cadetblue' => '#5f9ea0', 'chartreuse' => '#7fff00', 'chocolate' => '#d2691e',
		'coral' => '#ff7f50', 'cornflowerblue' => '#6495ed', 'cornsilk' => '#fff8dc',
		'crimson' => '#dc143c', 'cyan' => '#00ffff', 'darkblue' => '#00008b', 'darkcyan' => '#008b8b',
		'darkgoldenrod' => '#b8860b', 'darkgray' => '#a9a9a9', 'darkgreen' => '#006400',
		'darkgrey' => '#a9a9a9', 'darkkhaki' => '#bdb76b', 'darkmagenta' => '#8b008b',
		'darkolivegreen' => '#556b2f', 'darkorange' => '#ff8c00', 'darkorchid' => '#9932cc',
		'darkred' => '#8b0000', 'darksalmon' => '#e9967a', 'darkseagreen' => '#8fbc8f',
		'darkslateblue' => '#483d8b', 'darkslategray' => '#2f4f4f', 'darkslategrey' => '#2f4f4f',
		'darkturquoise' => '#00ced1', 'darkviolet' => '#9400d3', 'deeppink' => '#ff1493',
		'deepskyblue' => '#00bfff', 'dimgray' => '#696969', 'dimgrey' => '#696969',
		'dodgerblue' => '#1e90ff', 'firebrick' => '#b22222', 'floralwhite' => '#fffaf0',
		'forestgreen' => '#228b22', 'fuchsia' => '#ff00ff', 'gainsboro' => '#dcdcdc',
		'ghostwhite' => '#f8f8ff', 'gold' => '#ffd700', 'goldenrod' => '#daa520', 'gray' => '#808080',
		'green' => '#008000', 'greenyellow' => '#adff2f', 'grey' => '#808080', 'honeydew' => '#f0fff0',
		'hotpink' => '#ff69b4', 'indianred' => '#cd5c5c', 'indigo' => '#4b0082', 'ivory' => '#fffff0',
		'khaki' => '#f0e68c', 'lavender' => '#e6e6fa', 'lavenderblush' => '#fff0f5',
		'lawngreen' => '#7cfc00', 'lemonchiffon' => '#fffacd', 'lightblue' => '#add8e6',
		'lightcoral' => '#f08080', 'lightcyan' => '#e0ffff', 'lightgoldenrodyellow' => '#fafad2',
		'lightgray' => '#d3d3d3', 'lightgreen' => '#90ee90', 'lightgrey' => '#d3d3d3',
		'lightpink' => '#ffb6c1', 'lightsalmon' => '#ffa07a', 'lightseagreen' => '#20b2aa',
		'lightskyblue' => '#87cefa', 'lightslategray' => '#778899', 'lightslategrey' => '#778899',
		'lightsteelblue' => '#b0c4de', 'lightyellow' => '#ffffe0', 'lime' => '#00ff00',
		'limegreen' => '#32cd32', 'linen' => '#faf0e6', 'magenta' => '#ff00ff', 'maroon' => '#800000',
		'mediumaquamarine' => '#66cdaa', 'mediumblue' => '#0000cd', 'mediumorchid' => '#ba55d3',
		'mediumpurple' => '#9370db', 'mediumseagreen' => '#3cb371', 'mediumslateblue' => '#7b68ee',
		'mediumspringgreen' => '#00fa9a', 'mediumturquoise' => '#48d1cc', 'mediumvioletred' => '#c71585',
		'midnightblue' => '#191970', 'mintcream' => '#f5fffa', 'mistyrose' => '#ffe4e1',
		'moccasin' => '#ffe4b5', 'navajowhite' => '#ffdead', 'navy' => '#000080', 'oldlace' => '#fdf5e6',
		'olive' => '#808000', 'olivedrab' => '#6b8e23', 'orange' => '#ffa500', 'orangered' => '#ff4500',
		'orchid' => '#da70d6', 'palegoldenrod' => '#eee8aa', 'palegreen' => '#98fb98',
		'paleturquoise' => '#afeeee', 'palevioletred' => '#db7093', 'papayawhip' => '#ffefd5',
		'peachpuff' => '#ffdab9', 'peru' => '#cd853f', 'pink' => '#ffc0cb', 'plum' => '#dda0dd',
		'powderblue' => '#b0e0e6', 'purple' => '#800080', 'rebeccapurple' => '#663399',
		'red' => '#ff0000', 'rosybrown' => '#bc8f8f', 'royalblue' => '#4169e1',
		'saddlebrown' => '#8b4513', 'salmon' => '#fa8072', 'sandybrown' => '#f4a460',
		'seagreen' => '#2e8b57', 'seashell' => '#fff5ee', 'sienna' => '#a0522d', 'silver' => '#c0c0c0',
		'skyblue' => '#87ceeb', 'slateblue' => '#6a5acd', 'slategray' => '#708090',
		'slategrey' => '#708090', 'snow' => '#fffafa', 'springgreen' => '#00ff7f',
		'steelblue' => '#4682b4', 'tan' => '#d2b48c', 'teal' => '#008080', 'thistle' => '#d8bfd8',
		'tomato' => '#ff6347', 'turquoise' => '#40e0d0', 'violet' => '#ee82ee', 'wheat' => '#f5deb3',
		'white' => '#ffffff', 'whitesmoke' => '#f5f5f5', 'yellow' => '#ffff00',
		'yellowgreen' => '#9acd32',
	];

	private string $domain;
	private int $hue;

	public function __construct(string $domain, ?int $hue = null)
	{
		$domain = strtolower(rtrim(trim($domain), '.'));
		if (function_exists('idn_to_ascii')) {
			$domain = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: $domain;
		}
		$this->domain = $domain;
		if ($hue === null) {
			$this->hue = $this->rng('hue')->getInt(0, 359);
		} else {
			$this->hue = (($hue % 360) + 360) % 360;
		}
	}

	public function hue(): int
	{
		return $this->hue;
	}

	/** Independent, reproducible RNG stream per purpose. */
	private function rng(string $purpose): SeededRng
	{
		return new SeededRng($purpose . '|' . $this->domain);
	}

	/**
	 * The background tile's shapes, decided once so the SVG and raster versions match.
	 *
	 * @return list<array{x:int, y:int, r:int, h:int, l:int, type:string, angle:int}>
	 */
	private function bgShapes(int $size): array
	{
		$rng = $this->rng('bg');
		$shapes = [];
		$n = $rng->getInt(6, 12);
		for ($i = 0; $i < $n; $i++) {
			$s = [];
			$s['x'] = $rng->getInt(0, $size - 1);
			$s['y'] = $rng->getInt(0, $size - 1);
			$s['r'] = $rng->getInt(4, intdiv($size, 5));
			$s['h'] = ($this->hue + $rng->getInt(-30, 30) + 360) % 360;
			$s['l'] = $rng->getInt(...self::BG_SHAPE_LIGHTNESS);
			$s['type'] = $rng->getInt(0, 1) ? 'circle' : 'square';
			$s['angle'] = $s['type'] === 'square' ? $rng->getInt(0, 89) : 0;
			$shapes[] = $s;
		}
		return $shapes;
	}

	/** The 9 positions each shape is drawn at so the tile repeats seamlessly, (0,0) first. */
	private static function wrapOffsets(int $size): array
	{
		$offsets = [[0, 0]];
		foreach ([-$size, 0, $size] as $dx) {
			foreach ([-$size, 0, $size] as $dy) {
				if ($dx || $dy) {
					$offsets[] = [$dx, $dy];
				}
			}
		}
		return $offsets;
	}

	/** Seamless repeating tile as SVG. */
	public function backgroundSvg(int $size = self::TILE): string
	{
		$shapes = '';
		foreach ($this->bgShapes($size) as $s) {
			$shapes .= $s['type'] === 'circle'
				? "<circle cx=\"{$s['x']}\" cy=\"{$s['y']}\" r=\"{$s['r']}\" fill=\"hsl({$s['h']},40%,{$s['l']}%)\" fill-opacity=\".6\"/>"
				: sprintf('<rect x="%d" y="%d" width="%d" height="%d" transform="rotate(%d %d %d)" fill="hsl(%d,40%%,%d%%)" fill-opacity=".6"/>',
					$s['x'] - $s['r'], $s['y'] - $s['r'], 2 * $s['r'], 2 * $s['r'], $s['angle'], $s['x'], $s['y'], $s['h'], $s['l']);
		}

		// Re-draw the group at the 8 neighbouring offsets so edge shapes wrap.
		$uses = '';
		foreach (array_slice(self::wrapOffsets($size), 1) as [$dx, $dy]) {
			$uses .= "<use href=\"#t\" x=\"$dx\" y=\"$dy\"/>";
		}

		return "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"$size\" height=\"$size\" viewBox=\"0 0 $size $size\">"
			. "<rect width=\"100%\" height=\"100%\" fill=\"hsl({$this->hue},30%," . self::BG_LIGHTNESS . "%)\"/>"
			. "<g id=\"t\">$shapes</g>$uses</svg>";
	}

	/**
	 * Seamless repeating tile as PNG or GIF, or null if no backend can write that format.
	 * $raster: null = auto-detect, false = force "no raster", or a specific backend.
	 */
	public function background(string $format = 'png', int $size = self::TILE, Raster|false|null $raster = null): ?string
	{
		$raster ??= RasterFactory::detect($format);
		if (!$raster) {
			return null;
		}

		$scene = RasterFactory::scene($size, $size, self::hslToRgb($this->hue, .30, self::BG_LIGHTNESS / 100));
		$shapes = $this->bgShapes($size);
		// Same drawing order as the SVG: every shape at (0,0), then each wrapped copy.
		foreach (self::wrapOffsets($size) as [$dx, $dy]) {
			foreach ($shapes as $s) {
				$cx = $s['x'] + $dx;
				$cy = $s['y'] + $dy;
				$reach = $s['type'] === 'square' ? $s['r'] * M_SQRT2 : $s['r'];
				if ($cx + $reach < 0 || $cx - $reach >= $size || $cy + $reach < 0 || $cy - $reach >= $size) {
					continue; // this copy lands entirely outside the tile
				}
				$rgb = self::hslToRgb($s['h'], .40, $s['l'] / 100);
				if ($s['type'] === 'circle') {
					$scene->circle($cx, $cy, $s['r'], $rgb, .6);
				} else {
					$scene->polygon(self::rotatedSquare($cx, $cy, $s['r'], $s['angle']), $rgb, .6);
				}
			}
		}
		return $raster->render($scene, $format);
	}

	/** Corners of a square of half-width $r centred on ($cx, $cy), rotated clockwise like SVG's rotate(). */
	private static function rotatedSquare(float $cx, float $cy, float $r, int $degrees): array
	{
		$a = deg2rad($degrees);
		$cos = cos($a);
		$sin = sin($a);
		$points = [];
		foreach ([[-$r, -$r], [$r, -$r], [$r, $r], [-$r, $r]] as [$dx, $dy]) {
			$points[] = [$cx + $dx * $cos - $dy * $sin, $cy + $dx * $sin + $dy * $cos];
		}
		return $points;
	}

	/** 5x5 horizontally mirrored identicon grid. */
	private function grid(): array
	{
		$rng = $this->rng('icon');
		$g = [];
		for ($y = 0; $y < 5; $y++) {
			for ($x = 0; $x < 3; $x++) {
				$g[$y][$x] = $g[$y][4 - $x] = (bool) $rng->getInt(0, 1);
			}
		}
		return $g;
	}

	public function faviconSvg(): string
	{
		$rects = '';
		foreach ($this->grid() as $y => $row) {
			foreach ($row as $x => $on) {
				if ($on) {
					$rects .= "<rect x=\"$x\" y=\"$y\" width=\"1\" height=\"1\"/>";
				}
			}
		}
		return "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"-.5 -.5 6 6\" shape-rendering=\"crispEdges\">"
			. "<g fill=\"hsl({$this->hue},55%,45%)\">$rects</g></svg>";
	}

	/** Pixel rectangles for the identicon at a given size. */
	private function iconRects(int $px): array
	{
		$pad = $px / 12;
		$cell = ($px - 2 * $pad) / 5;
		$rects = [];
		foreach ($this->grid() as $y => $row) {
			foreach ($row as $x => $on) {
				if ($on) {
					$rects[] = [
						(int) round($pad + $x * $cell), (int) round($pad + $y * $cell),
						(int) round($pad + ($x + 1) * $cell) - 1, (int) round($pad + ($y + 1) * $cell) - 1,
					];
				}
			}
		}
		return $rects;
	}

	/**
	 * Icon as PNG or GIF (transparent background), or null if no backend can write that format.
	 * $raster: null = auto-detect, false = force "no raster", or a specific backend.
	 */
	public function favicon(int $px = 32, string $format = 'png', Raster|false|null $raster = null): ?string
	{
		$raster ??= RasterFactory::detect($format);
		if (!$raster) {
			return null;
		}
		$scene = RasterFactory::scene($px, $px);
		$rgb = self::hslToRgb($this->hue, .55, .45);
		foreach ($this->iconRects($px) as [$x0, $y0, $x1, $y1]) {
			$scene->rect($x0, $y0, $x1, $y1, $rgb);
		}
		return $raster->render($scene, $format);
	}

	/** Kept so older callers still work; same as favicon($px, 'png', $raster). */
	public function faviconPng(int $px = 32, Raster|false|null $raster = null): ?string
	{
		return $this->favicon($px, 'png', $raster);
	}

	/**
	 * Icon as a standalone 16-colour .bmp, in pure PHP (no image library needed).
	 * BMP has no transparency, so the background is the tile's base colour.
	 */
	public function faviconBmp(int $px = 32): string
	{
		$dib = $this->iconDib($px, self::hslToRgb($this->hue, .30, .95), false);
		// BITMAPFILEHEADER: "BM", file size, 2 reserved words, offset to the pixels.
		return 'BM' . pack('VvvV', 14 + strlen($dib), 0, 0, 14 + 40 + 16 * 4) . $dib;
	}

	/**
	 * favicon.ico with one entry per size. 'bmp' (the default) uses classic 16-colour
	 * bitmaps with a transparency mask, which every browser back to IE5 can read, and
	 * needs no image library. 'png' uses PNG entries (Vista and later only).
	 * Returns null only for 'png' when no backend can write PNG.
	 */
	public function faviconIco(array $sizes = [16, 32, 48], string $inner = 'bmp'): ?string
	{
		$entries = [];
		foreach ($sizes as $px) {
			if ($inner === 'png') {
				$png = $this->favicon($px);
				if ($png === null) {
					return null;
				}
				$entries[] = [$px, 32, 0, $png];
			} else {
				$entries[] = [$px, 4, 16, $this->iconDib($px, [0, 0, 0], true)];
			}
		}
		return self::ico($entries);
	}

	/** Which pixels of the icon are filled, as [y][x] => bool. */
	private function iconPixels(int $px): array
	{
		$on = array_fill(0, $px, array_fill(0, $px, false));
		foreach ($this->iconRects($px) as [$x0, $y0, $x1, $y1]) {
			for ($y = $y0; $y <= $y1; $y++) {
				for ($x = $x0; $x <= $x1; $x++) {
					$on[$y][$x] = true;
				}
			}
		}
		return $on;
	}

	/**
	 * A 4-bit (16-colour) device-independent bitmap of the icon: BITMAPINFOHEADER,
	 * palette, pixel rows, and for icons the 1-bit AND (transparency) mask.
	 * Palette entry 0 is the background, entry 1 the icon colour.
	 */
	private function iconDib(int $px, array $bgRgb, bool $forIco): string
	{
		$on = $this->iconPixels($px);
		$xorRow = (intdiv($px + 1, 2) + 3) & ~3;  // 2 pixels per byte, rows padded to 4 bytes
		$andRow = (intdiv($px + 7, 8) + 3) & ~3;  // 8 pixels per byte, rows padded to 4 bytes
		$pixels = '';
		$mask = '';
		for ($y = $px - 1; $y >= 0; $y--) {        // BMP rows run bottom to top
			$row = str_repeat("\0", $xorRow);
			$m = str_repeat("\0", $andRow);
			for ($x = 0; $x < $px; $x++) {
				if ($on[$y][$x]) {
					$i = $x >> 1;
					$row[$i] = chr(ord($row[$i]) | ($x & 1 ? 0x01 : 0x10));  // palette index 1
				} else {
					$i = $x >> 3;
					$m[$i] = chr(ord($m[$i]) | (0x80 >> ($x & 7)));          // 1 = transparent
				}
			}
			$pixels .= $row;
			$mask .= $m;
		}
		$data = $forIco ? $pixels . $mask : $pixels;

		// In an icon, transparent pixels are drawn as (screen AND mask) XOR colour,
		// so their colour must be black; that's why callers pass [0,0,0] for icons.
		$palette = '';
		$colors = [$bgRgb, self::hslToRgb($this->hue, .55, .45)];
		for ($i = 0; $i < 16; $i++) {
			[$r, $g, $b] = $colors[$i] ?? [0, 0, 0];
			$palette .= pack('CCCC', $b, $g, $r, 0);  // stored as blue, green, red, reserved
		}

		// BITMAPINFOHEADER. Icons store double height: the colour rows plus the mask rows.
		$header = pack('VVVvvVVVVVV',
			40, $px, $forIco ? 2 * $px : $px, 1, 4, 0, strlen($data), 2835, 2835, 16, 0);
		return $header . $palette . $data;
	}

	/** Build a .ico from PNG payloads (PNG-in-ICO; Vista and later). */
	public static function pngsToIco(array $pngsBySize): string
	{
		$entries = [];
		foreach ($pngsBySize as $px => $png) {
			$entries[] = [$px, 32, 0, $png];
		}
		return self::ico($entries);
	}

	/** @param list<array{int,int,int,string}> $entries  [size, bits per pixel, palette size, image data] */
	private static function ico(array $entries): string
	{
		$out = pack('vvv', 0, 1, count($entries));  // reserved, type 1 = icon, count
		$offset = 6 + 16 * count($entries);
		$data = '';
		foreach ($entries as [$px, $bpp, $colors, $img]) {
			$dim = $px >= 256 ? 0 : $px;
			$out .= pack('CCCCvvVV', $dim, $dim, $colors, 0, 1, $bpp, strlen($img), $offset);
			$offset += strlen($img);
			$data .= $img;
		}
		return $out . $data;
	}

	public static function hslToRgb(float $h, float $s, float $l): array
	{
		$f = function (int $n) use ($h, $s, $l): int {
			$k = fmod($n + $h / 30, 12);
			$a = $s * min($l, 1 - $l);
			return (int) round(255 * ($l - $a * max(-1, min($k - 3, 9 - $k, 1))));
		};
		return [$f(0), $f(8), $f(4)];
	}

	/**
	 * [r, g, b] of a "#rgb"/"#rrggbb" hex string or a CSS/HTML colour name such as "tomato".
	 */
	public static function rgbOf(string $color): array
	{
		$hex = ltrim(self::NAMED_COLORS[strtolower(trim($color))] ?? trim($color), '#');
		if (strlen($hex) === 3) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if (!preg_match('/^[0-9a-f]{6}$/i', $hex)) {
			throw new InvalidArgumentException("Not a colour name or hex value: $color");
		}
		return array_map('hexdec', str_split($hex, 2));
	}

	/**
	 * Hue (0-359) of an [r, g, b] array, a "#rgb"/"#rrggbb" hex string,
	 * or a CSS/HTML colour name such as "tomato". Greys have no hue, so they return 0.
	 */
	public static function hueOf(array|string $color): int
	{
		if (is_string($color)) {
			$color = self::rgbOf($color);
		}
		[$r, $g, $b] = $color;
		$max = max($r, $g, $b);
		$d = $max - min($r, $g, $b);
		if ($d == 0) {
			return 0;
		}
		$h = match ($max) {
			$r => fmod(($g - $b) / $d, 6),
			$g => ($b - $r) / $d + 2,
			default => ($r - $g) / $d + 4,
		};
		return ((int) round($h * 60) % 360 + 360) % 360;
	}
}

/**
 * Deterministic integer generator: SHA-256 in counter mode.
 * Same seed gives the same sequence on every PHP version and platform.
 */
final class SeededRng
{
	private string $seed;
	private string $pool = '';
	private int $counter = 0;

	public function __construct(string $seed)
	{
		$this->seed = hash('sha256', $seed, true);
	}

	public function getInt(int $min, int $max): int
	{
		$range = $max - $min + 1;
		// Rejection sampling so every value in the range is equally likely.
		$limit = intdiv(0x100000000, $range) * $range;
		do {
			if (strlen($this->pool) < 4) {
				$this->pool .= hash('sha256', $this->seed . pack('N', $this->counter++), true);
			}
			$n = unpack('N', substr($this->pool, 0, 4))[1];
			$this->pool = substr($this->pool, 4);
		} while ($n >= $limit);
		return $min + $n % $range;
	}
}
