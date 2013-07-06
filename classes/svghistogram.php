<?php
/** Create a dinky little graph from data representing values over time.

	Copyright (C) 2013 Justin Wind <justin.wind@gmail.com> under GPL

	Derived from code copyright (C) 2002 J. David Eisenberg under GPL
*/

define('SVGHistogramPHI', (1 + sqrt(5)) / 2);
class SVGHistogram {
	const PHI = SVGHistogramPHI;

	var $options = array(
		'frame_color' => null,
		'min_items' => '100',
		'max_items' => null,
		'title' => '',
		'description' => '',
		'bar_width' => 20,
		'bar_height' => 100,
		'tick_every' => 7,
		'shade_over' => null,
		'shade_color' => 'red',
		'label_zero' => null,
		'label_x' => null,
		'label_height' => 8
	);

	function __construct(array $options=array()) {
		$this->options = array_merge($this->options, $options);
	}

	/** Beginning of SVG output.
	*/
	protected function svg_header($width, $height) {
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
		$header .= '	<title>' . htmlentities($this->options['title'], ENT_NOQUOTES|ENT_XML1) . '</title>' . "\n";
		$header .= '	<desc>' . htmlentities($this->options['description'], ENT_NOQUOTES|ENT_XML1) . '</desc>' . "\n";

		return $header;
	}

	/** End of SVG output.
	*/
	protected function svg_footer($width, $height) {
		$footer = '</svg>' . "\n";

		return $footer;
	}

	/** Draw a frame around the graph.
	*/
	protected function svg_frame($width, $height) {
		if (empty($this->options['frame_color']))
			return '';

		$frame .= '	<g id="frame">' . "\n";
		$frame .= '		<rect x="0.5px" y="0.5px" width="' . ($width - 1) . 'px" height="' . ($this->options['bar_height'] - 1) . 'px" fill="none" stroke="' . $this->options['frame_color'] . '" />' . "\n";
		$frame .= '	</g>' . "\n";

		return $frame;
	}

	/** Draw ticks on the graph.
	*/
	protected function svg_ticks($width, $height) {
		if (empty($this->options['tick_every']))
			return '';

		$tick_spacing = $this->options['tick_every'] * $this->options['bar_width'];
		$tick_paths = array();
		for ($i = $tick_spacing; $i < $width; $i += $tick_spacing) {
			$tick_paths[] = sprintf('M %d %d v %d', $i, ($this->options['bar_height'] - 2), 4);
		}
		$ticks = '	<g id="ticks">' . "\n";
		$ticks .= '		<path d="' . implode(' ', $tick_paths) . '" stroke="gray" fill="none" stroke-width="0.5px" />' . "\n";
		$ticks .= '	</g>' . "\n";

		return $ticks;
	}

	/** Draw a shaded region over items greater than n-th.
	*/
	protected function svg_shade($width, $height) {
		if (empty($this->options['shade_over']))
			return '';

		$shade_tick = $this->options['shade_over'] * $this->options['bar_width'];
		if ($shade_tick >= $width)
			return '';

		$shade = '	<g id="shade">' . "\n";
		$shade_path = sprintf('M %d %d v %d', $shade_tick, 0, $this->options['bar_height']);
		$shade .= '		<path d="' . $shade_path . '" stroke="' . $this->options['shade_color'] . '" fill="none" stroke-width="0.5px" />' . "\n";
		$shade .= '		<rect x="' . $shade_tick . 'px" y="0px" width="' . ($width - $shade_tick) . 'px" height="' . $this->options['bar_height'] . 'px" stroke="none" fill="' . $this->options['shade_color'] . '" fill-opacity="0.15" />' . "\n";
		$shade .= '	</g>' . "\n";

		return $shade;
	}

	/** Convert an item's value as a percent of the maximum value into a color
		suitable for use in a style.
		$percent should be a float in the range [0,1]
	*/
	protected function value_to_color($percent) {
		return 'rgb(' . implode(', ', array_map(function($n) {
			return round($n * 100) . '%';
		}, array_fill(0, 3, 0.95 * (1.0 - $percent)))) . ')';
	}

	/** Draw a bar representing an item's value on the graph.
		This renders the value as full bar, shaded gray in relation to the value.
		An alternative implementation might render a bar of varying height.
		$ordinality provides the offset
		$value should be between 0 and $max
		$max should be the uppermost value of the scale
	*/
	protected function svg_bar($ordinality, $value, $max) {
		$bar = '<rect';
		$bar .= ' id="i' . $ordinality . '"';
		$bar .= ' x="' . $this->options['bar_width'] * $ordinality . '"';
		$bar .= ' y="0"';
		$bar .= ' width="' . $this->options['bar_width'] . '"';
		$bar .= ' height="' . $this->options['bar_height'] . '"';
		$bar .= ' fill="' . $this->value_to_color($value / $max) . '"';
		$bar .= ' />';

		return $bar;
	}

	/** Draw an indicator of more-but-unrendered items.
	*/
	protected function svg_spillage($count, $sum, $max) {
		if (empty($count)
		||  empty($this->options['max_items']))
			return '';

		$points = array();
		$x = $this->options['max_items'] * $this->options['bar_width'];
		$points[] = $x . ',0';
		$points[] = $x . ',' . $this->options['bar_height'];
		$points[] = ($x + ceil($this->options['bar_width'] * self::PHI)) . ',' . round($this->options['bar_height'] / 2);
		$spill ='<polygon';
		$spill .= ' id="spillage"';
		$spill .= ' fill="' . $this->value_to_color(($count / $sum) / $max) . '"';
		$spill .= ' stroke="none"';
		$spill .= ' points="' . implode(' ', $points) . ',"';
		$spill .= ' />' . "\n";

		return $spill;
	}


	protected function svg_label_zero($width, $height) {
		if (empty($this->options['label_zero']))
			return '';

		$label = '		<text text-anchor="start" x="0" y="' . ($height - ($this->options['label_height'] - 2)) . '">';
		$label .= $this->options['label_zero'];
		$label .= '</text>' . "\n";

		return $label;
	}

	protected function svg_label_x($width, $height) {
		if (empty($this->options['label_x']))
			return '';

		$label = '		<text text-anchor="middle" x="' . round($width / 2) . '" y="' . ($height - ($this->options['label_height'] - 2)) . '">';
		$label .= $this->options['label_x'];
		$label .= '</text>' . "\n";

		return $label;
	}


	/** Generate a graph from $values.
	*/
	function render($values) {
		$count = count($values);
		$no_labels = empty($this->options['label_zero']) && empty($this->options['label_x']);
		$height = $this->options['bar_height'];
		$height += $no_labels ? 2 : ($this->options['label_height'] + 6);
		$width = ($count < $this->options['min_items'] ? $this->options['min_items'] : $count) * $this->options['bar_width'];
		if ( ! empty($this->options['max_items'])) {
			$max_width = $this->options['max_items'] * $this->options['bar_width'];
			$width = $max_width + ceil($this->options['bar_width'] * self::PHI);
		}

		echo $this->svg_header($width, $height);

		$spilt_count = 0;
		$spilt_sum = 0;
		$max = $count ? max($values) : 0;
		foreach ($values as $ord => $val) {
			if ( ! empty($this->options['max_items'])
			&&  $ord >= $this->options['max_items']) {
				$spilt_count ++;
				$spilt_sum += $val;
			} else {
				echo '	' . $this->svg_bar($ord, $val, $max) . "\n";
			}
		}
		echo $this->svg_spillage($spilt_count, $spilt_sum, $max);
		echo $this->svg_frame($width, $height);
		echo $this->svg_ticks($width, $height);
		echo $this->svg_shade($width, $height);
		if ( ! $no_labels)
			echo '	<g font-family="sans-serif" font-variant="small-caps" font-size="' . $this->options['label_height'] . 'px">' . "\n";
		echo $this->svg_label_zero($width, $height);
		echo $this->svg_label_x($width, $height);
		if ( ! $no_labels)
			echo '	</g>' . "\n";
		echo $this->svg_footer($width, $height);
	}

}
?>
