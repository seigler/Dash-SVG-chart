<?php
namespace NeatCharts {
  abstract class NeatChart {
    protected $options = [
      'width' => 800,
      'height' => 250,
      'lineColor' => '#000',
      'markerColor' => '#000',
      'labelColor' => '#000',
      'smoothed' => false,
      'fontSize' => 15,
      'yAxisEnabled'=>true,
      'xAxisEnabled'=>false,
      'yAxisZero'=>false
    ];

    protected $width;
    protected $height;
    protected $output;
    protected $xMin;
    protected $xMax;
    protected $xRange;
    protected $yMin;
    protected $yMax;
    protected $yRange;
    protected $padding = ['top'=>10, 'right'=>10, 'bottom'=>10, 'left'=>10];

    protected function labelFormat($float, $places, $minPlaces = 0) {
      $value = number_format($float, max($minPlaces, $places));
      // add a trailing space if there's no decimal
      return (strpos($value, '.') === false ? $value . '.' : $value);
    }

    /* Transform data coords to chart coords */
    /* Transform data coords to chart coords */
    protected function transformX($x) {
      return round(
        ($x - $this->xMin) / $this->xRange * $this->width
      , 2);
    }
    protected function transformY($y) {
      return round(
      // SVG has y axis reversed, 0 is at the top
        ($this->yMax - $y) / $this->yRange * $this->height
      , 2);
    }

    protected function getPrecision($value) { // thanks http://stackoverflow.com/a/21788335/5402566
      if (!is_numeric($value)) { return false; }
      $decimal = $value - floor($value); //get the decimal portion of the number
      if ($decimal == 0) { return 0; } //if it's a whole number
      $precision = strlen(trim(number_format($decimal,10),'0')) - 1; //-2 to account for '0.'
      return $precision;
    }

    protected function setWindow($chartData) {
      end($chartData);
      $this->xMax = key($chartData);
      reset($chartData);
      $this->xMin = key($chartData);
      $this->xRange = $this->xMax - $this->xMin;
      $this->yMin = ($this->options['yAxisZero'] ? 0 : INF);
      $this->yMax = -INF;

      foreach ($chartData as $x => $y) {
        if ($y < $this->yMin) {
          $this->yMin = $y;
          $yMinX = $x;
        }
        if ($y > $this->yMax) {
          $this->yMax = $y;
          $yMaxX = $x;
        }
      }
      $this->yRange = $this->yMax - $this->yMin;
    }

    protected function buildGridLabelXML() {
      $this->width = $this->options['width'] - $this->padding['left'] - $this->padding['right'];
      $this->height = $this->options['height'] - $this->padding['top'] - $this->padding['bottom'];
      if ($this->options['yAxisEnabled'] || $this->options['xAxisEnabled']) {
        $numLabels = 2 + ceil($this->height / $this->options['fontSize'] / 6);
        $labelInterval = $this->yRange / $numLabels;
        $labelModulation = 10 ** (1 + floor(-log($this->yRange / $numLabels, 10)));
        // 0.1 here is a fudge factor so we get multiples of 2.5 a little more often
        if (fmod($labelInterval * $labelModulation, 2.5) < fmod($labelInterval * $labelModulation, 2) + 0.1) {
          $labelModulation /= 2.5;
        } else {
          $labelModulation /= 2;
        }
        $labelInterval = ceil($labelInterval * $labelModulation) / $labelModulation;
        $labelPrecision = $this->getPrecision($labelInterval);

        $this->padding['left'] = $this->options['fontSize'] * 0.6 * (
          1 + max(1, ceil(log($this->yMax, 10))) + $this->getPrecision($labelInterval)
        ) + 10;
        $this->width = $this->options['width'] - $this->padding['left'] - $this->padding['right'];

        // Top and bottom grid lines
        $gridLines =
          'M10,0 '.$this->width.',0 '.
          ' M10,'.$this->height.','.$this->width.','.$this->height;

        // Top and bottom grid labels
        $gridText =
          '<text text-anchor="end" x="'.(0.4 * $this->options['fontSize']).'" y="'.($this->options['fontSize'] * 0.4).'">'.($this->labelFormat($this->yMax, $labelPrecision + 1)).'</text>' .
          '<text text-anchor="end" x="'.(0.4 * $this->options['fontSize']).'" y="'.($this->options['fontSize'] * 0.4 + $this->height).'">'.($this->labelFormat($this->yMin, $labelPrecision + 1)).'</text>';

        // Main labels and grid lines
        for (
          $labelY = $this->yMin - fmod($this->yMin, $labelInterval) + $labelInterval; // Start at the first "nice" Y value > min
          $labelY < $this->yMax; // Keep going until max
          $labelY += $labelInterval // Add Interval each iteration
        ) {
          $labelHeight = $this->transformY($labelY);
          if ( // label is not too close to the min or max
            $labelHeight < $this->height - 1.5 * $this->options['fontSize'] &&
            $labelHeight > $this->options['fontSize'] * 1.5
          ) {
            $gridText .= '<text text-anchor="end" x="-'.(0.25 * $this->options['fontSize']).'" y="'.($labelHeight + $this->options['fontSize'] * 0.4).'">'.$this->labelFormat($labelY, $labelPrecision).'</text>';
            $gridLines .= ' M0,'.$labelHeight.' '.$this->width.','.$labelHeight;
          } else if ( // label is too close
            $labelHeight < $this->height - $this->options['fontSize'] * 0.75 &&
            $labelHeight > $this->options['fontSize'] * 0.75
          ) {
            $gridLines .= ' M'.( // move grid line over when it's very close to the min or max label
              $labelHeight < $this->height - $this->options['fontSize'] / 2 && $labelHeight > $this->options['fontSize'] / 2 ? 0 : $this->options['fontSize'] / 2
            ).','.$labelHeight.' '.$this->width.','.$labelHeight;
          }
        }

        return '
        <g class="chart__gridLines"
          fill="none"
          stroke="'.( $this->options['labelColor'] ).'"
          stroke-width="1"
          vector-effect="non-scaling-stroke"
          stroke-dasharray="2, 2"
          shape-rendering="crispEdges">
          <path class="chart__gridLinePaths" d="'.( $gridLines ).'" />
        </g>
        <g class="chart__gridLabels"
          fill="'.( $this->options['labelColor'] ).'"
          font-family="monospace"
          font-size="'.( $this->options['fontSize'] ).'px">
          '.( $gridText ).'
        </g>';
      } else {
        return '';
      }
    }

    final public function __construct($chartData, $options = []) {
      $this->setOptions($options);
      $this->setData($chartData);
    }

    public function setOptions($options) {
      $this->options = array_replace($this->options, $options);
      $this->padding['left'] = $this->padding['right'] = $this->options['fontSize'] / 2;
      $this->padding['top'] = $this->padding['bottom'] = $this->options['fontSize'];
    }

    public abstract function setData($chartData);
    public function render() {
      return $this->output;
    }
  }
}
