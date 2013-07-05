<?php
/** Create a dinky little graph from data representing values over time.

	Copyright (C) 2013 Justin Wind <justin.wind@gmail.com> under GPL

	Derived from code copyright (C) 2002 J. David Eisenberg under GPL
*/

define('SVGHistogramPHI', (1 + sqrt(5)) / 2);
class SVGHistogram {
	const PHI = SVGHistogramPHI;

	/** Class defaults.
	*/
	var $options = array(
		'frame_color' => null,
		'min_items' => '100',
		'max_items' => null,
		'spill_caption' => 'Not shown: %2$d items over %1$d more days',
		'title' => '',
		'description' => '',
		'bar_width' => 5,
		'bar_height' => 20,
		'bar_caption' => '%1$d days ago, %2$d items',
		'tick_every' => 7,
		'shade_over' => null,
		'shade_color' => 'red',
		'label_zero' => null,
		'label_x' => null,
		'label_height' => 8
	);

	/** Instance settings are derived from class defaults.
	*/
	function __construct(array $options=array()) {
		$this->options = array_merge($this->options, $options);
	}

	/** Convert an item's value as a percent of the maximum value into a color
		suitable for use in a style.
		$percent should be a float in the range [0,1]
	*/
	static protected function value_to_color($percent) {
		return 'rgb(' . implode(', ', array_map(function($n) {
			return round($n * 100) . '%';
		}, array_fill(0, 3, 0.95 * (1.0 - $percent)))) . ')';
	}

	/** Beginning of SVG output.
	*/
	protected function svg_header($options, $width, $height) {
		$header = '<svg
 version="1.1"
 baseProfile="full"
 xmlns="http://www.w3.org/2000/svg"
 xmlns:xlink="http://www.w3.org/1999/xlink"
 xmlns:ev="http://www.w3.org/2001/xml-events"
 width="' . $width . 'px" height="' . $height . 'px"
 viewBox="0 0 ' . $width . ' ' . $height . '"
 preserveAspectRatio="xMinYMin meet">
';
		if ( ! is_null($options['title']))
			$header .= '	<title>' . htmlentities($options['title'], ENT_NOQUOTES|ENT_XML1) . '</title>' . "\n";

		if ( ! is_null($options['description']))
			$header .= '	<desc>' . htmlentities($options['description'], ENT_NOQUOTES|ENT_XML1) . '</desc>' . "\n";

		return $header;
	}

	/** End of SVG output.
	*/
	protected function svg_footer($options, $width, $height) {
		$footer = '</svg>' . "\n";

		return $footer;
	}

	/** Draw a frame around the graph.
	*/
	protected function svg_frame($options, $width, $height) {
		if (empty($options['frame_color']))
			return '';

		$frame .= '	<g id="frame">' . "\n"; 
		$frame .= '		<rect x="0.5px" y="0.5px" width="' . ($width - 1) . 'px" height="' . ($options['bar_height'] - 1) . 'px" fill="none" stroke="' . $options['frame_color'] . '" />' . "\n";
		$frame .= '	</g>' . "\n";

		return $frame;
	}

	/** Draw ticks on the graph.
	*/
	protected function svg_ticks($options, $width, $height) {
		if (empty($options['tick_every']))
			return '';

		$tick_spacing = $options['tick_every'] * $options['bar_width'];
		$tick_paths = array();
		for ($i = $tick_spacing; $i < $width; $i += $tick_spacing) {
			$tick_paths[] = sprintf('M %d %d v %d', $i, ($options['bar_height'] - 2), 4); 
		}
		$ticks = '	<g id="ticks">' . "\n";
		$ticks .= '		<path d="' . implode(' ', $tick_paths) . '" stroke="gray" fill="none" stroke-width="0.5px" />' . "\n";
		$ticks .= '	</g>' . "\n";

		return $ticks;
	}

	/** Draw a shaded region over items greater than n-th.
	*/
	protected function svg_shade($options, $width, $height) {
		if (empty($options['shade_over']))
			return '';

		$shade_tick = $options['shade_over'] * $options['bar_width'];
		if ($shade_tick >= $width)
			return '';

		$shade = '	<g id="shade">' . "\n";
		$shade_path = sprintf('M %d %d v %d', $shade_tick, 0, $options['bar_height']);
		$shade .= '		<path d="' . $shade_path . '" stroke="' . $options['shade_color'] . '" fill="none" stroke-width="0.5px" />' . "\n";
		$shade .= '		<rect x="' . $shade_tick . 'px" y="0px" width="' . ($width - $shade_tick) . 'px" height="' . $options['bar_height'] . 'px" stroke="none" fill="' . $options['shade_color'] . '" fill-opacity="0.15" />' . "\n";
		$shade .= '	</g>' . "\n";

		return $shade;
	}

	/** Draw a bar representing an item's value on the graph.
		This renders the value as full bar, shaded gray in relation to the value.
		An alternative implementation might render a bar of varying height.
		$ordinality provides the offset
		$value should be between 0 and $max
		$max should be the uppermost value of the scale
	*/
	protected function svg_bar($options, $ordinality, $value, $max) {
		$bar = '<rect';
		$bar .= ' id="i' . $ordinality . '"';
		$bar .= ' x="' . $options['bar_width'] * $ordinality . '"';
		$bar .= ' y="0"';
		$bar .= ' width="' . $options['bar_width'] . '"';
		$bar .= ' height="' . $options['bar_height'] . '"';
		$bar .= ' fill="' . self::value_to_color($value / $max) . '">';
		if ( ! empty($options['bar_caption'])) {
			$title = sprintf($options['bar_caption'], $ordinality, $value);
			$bar .= '<title>' . htmlentities($title, ENT_NOQUOTES|ENT_XML1) . '</title>';
		}
		$bar .= '</rect>';

		return $bar;
	}

	/** Draw an indicator of more-but-unrendered items.
	*/
	protected function svg_spillage($options, $count, $sum, $max) {
		if (empty($count)
		||  empty($options['max_items']))
			return '';

		$points = array();
		$x = $options['max_items'] * $options['bar_width'];
		$points[] = $x . ',0';
		$points[] = $x . ',' . $options['bar_height'];
		$points[] = ($x + ceil($options['bar_width'] * self::PHI)) . ',' . round($options['bar_height'] / 2);
		$spill = '<polygon';
		$spill .= ' id="spillage"';
		$spill .= ' fill="' . self::value_to_color(($sum / $count) / $max) . '"';
		$spill .= ' stroke="none"';
		$spill .= ' points="' . implode(' ', $points) . '">';
		if ( ! empty($options['spill_caption'])) {
			$title = sprintf($options['spill_caption'], $count, $sum);
			$spill .= '<title>' . htmlentities($title, ENT_NOQUOTES|ENT_XML1) . '</title>';
		}
		$spill .= '</polygon>' . "\n";

		return $spill;
	}


	protected function svg_label_zero($options, $width, $height) {
		if (empty($options['label_zero'])
		||  empty($options['label_height']))
			return '';

		$label = '		<text text-anchor="start" x="0" y="' . ($height - ($options['label_height'] - 2)) . '">';
		$label .= $options['label_zero'];
		$label .= '</text>' . "\n";

		return $label;
	}

	protected function svg_label_x($options, $width, $height) {
		if (empty($options['label_x'])
		||  empty($options['label_height']))
			return '';

		$label = '		<text text-anchor="middle" x="' . round($width / 2) . '" y="' . ($height - ($options['label_height'] - 2)) . '">';
		$label .= $options['label_x'];
		$label .= '</text>' . "\n";

		return $label;
	}


	/** Generate a graph from $values.
	*/
	function render($values, $options=array()) {
		$options = array_merge($this->options, $options);
		$count = count($values);
		$no_labels = (empty($options['label_zero']) && empty($options['label_x'])) || empty($options['label_height']);
		$height = $options['bar_height'];
		$height += $no_labels ? 2 : ($options['label_height'] + 6);
		$width = ($count < $options['min_items'] ? $options['min_items'] : $count) * $options['bar_width'];
		if ( ! empty($options['max_items'])) {
			$max_width = $options['max_items'] * $options['bar_width'];
			$width = $max_width + ceil($options['bar_width'] * self::PHI);
		}

		$graph = array();

		$graph[] = $this->svg_header($options, $width, $height);

		$spilt_count = 0;
		$spilt_sum = 0;
		$max = $count ? max($values) : 0;
		foreach ($values as $ord => $val) {
			if ( ! empty($options['max_items'])
			&&  $ord >= $options['max_items']) {
				$spilt_count ++;
				$spilt_sum += $val;
			} else {
				$graph[] = '	' . $this->svg_bar($options, $ord, $val, $max) . "\n";
			}
		}
		$graph[] = $this->svg_spillage($options, $spilt_count, $spilt_sum, $max);
		$graph[] = $this->svg_frame($options, $width, $height);
		$graph[] = $this->svg_ticks($options, $width, $height);
		$graph[] = $this->svg_shade($options, $width, $height);
		if ( ! $no_labels) {
			$graph[] = '	<g font-family="sans-serif" font-variant="small-caps" font-size="' . $options['label_height'] . 'px">' . "\n";
			$graph[] = $this->svg_label_zero($options, $width, $height);
			$graph[] = $this->svg_label_x($options, $width, $height);
			$graph[] = '	</g>' . "\n";
		}
		$graph[] = $this->svg_footer($options, $width, $height);

		echo implode($graph);
	}
}
?>
