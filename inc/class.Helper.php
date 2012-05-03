<?php
/**
 * Maximal heart-frequence of the user
 * @var HF_MAX
 */
define('HF_MAX', Helper::getHFmax());

/**
 * Heart-frequence in rest of the user
 * @var HF_REST
 */
define('HF_REST', Helper::getHFrest());

/**
 * Timestamp of the first training
 * @var START_TIME
 */
define('START_TIME', Helper::getStartTime());

/**
 * Year of the first training
 * @var START_YEAR
 */
define('START_YEAR', date("Y", START_TIME));

require_once FRONTEND_PATH.'calculate/class.JD.php';

/**
 * Class for all helper-functions previously done by functions.php
 * @author Hannes Christiansen <mail@laufhannes.de>
 */
class Helper {
	/**
	 * This class contains only static methods
	 */
	private function __construct() {}
	private function __destruct() {}

	/**
	 * Trim all values of an array
	 * @param array $array
	 * @return array 
	 */
	public static function arrayTrim($array) {
		array_walk($array, 'trimValuesForArray');

		return $array;
	}

	/**
	 * Get a string for displaying any pulse
	 * @param int $pulse
	 * @param int $time
	 * @return string
	 */
	public static function PulseString($pulse, $time = 0) {
		if ($pulse == 0)
			return '';

		$hf_max = 0;

		if ($time != 0) {
			$HFmax = Mysql::getInstance()->fetchSingle('SELECT * FROM `'.PREFIX.'user` ORDER BY ABS(`time`-'.$time.') ASC');
			if ($HFmax !== false && $HFmax['pulse_max'] != 0)
				$hf_max = $HFmax['pulse_max'];
		}

		$bpm = self::PulseStringInBpm($pulse);
		$hf  = self::PulseStringInPercent($pulse, $hf_max);

		if (CONF_PULS_MODE != 'bpm')
			return Ajax::tooltip($hf, $bpm);
			
		return Ajax::tooltip($bpm, $hf);
	}

	/**
	 * Get string for pulse [bpm]
	 * @param int $pulse
	 * @return string
	 */
	public static function PulseStringInBpm($pulse) {
		return round($pulse).'bpm';
	}

	/**
	 * Get string for pulse [%]
	 * @param int $pulse
	 * @param int $hf_max
	 * @return string
	 */
	public static function PulseStringInPercent($pulse, $hf_max = 0) {
		if ($hf_max == 0)
			$hf_max = HF_MAX;
		
		return round(100*$pulse / $hf_max).'&nbsp;&#37';
	}

	/**
	 * Get the speed depending on the sport as pace or km/h
	 * @uses self::Pace
	 * @uses self::Kmh
	 * @uses self::Sport
	 * @param float $km       Distance [km]
	 * @param int $time       Time [s]
	 * @param int $sport_id   ID of sport for choosing pace/kmh
	 * @return string
	 */
	public static function Speed($km, $time, $sport_id = 0) {
		if ($km == 0 || $time == 0)
			return '';

		$as_pace = self::Pace($km, $time).'/km';
		$as_kmh = self::Kmh($km, $time).'&nbsp;km/h';

		if (Sport::usesSpeedInKmh($sport_id))
			return Ajax::tooltip($as_kmh, $as_pace);
			
		return Ajax::tooltip($as_pace, $as_kmh);
	}

	/**
	 * Get the speed in min/km without unit
	 * @uses self::Time
	 * @param float $km   Distance [km]
	 * @param int $time   Time [s]
	 * @return string
	 */
	public static function Pace($km, $time) {
		if ($km == 0)
			return '-:--';

		return self::Time(round($time/$km));
	}

	/**
	 * Get the demanded pace if set in description (e.g. "... in 3:05 ...")
	 * @param string $description
	 * @return int
	 */
	public static function DescriptionToDemandedPace($description) {
		$array = explode("in ", $description);
		if (count($array) != 2)
			return 0;

		$array = explode(",", $array[1]);
		$array = explode(":", $array[0]);

		return sizeof($array) == 2 ? 60*$array[0]+$array[1] : 0;
	}

	/**
	 * Get the speed in km/h without unit
	 * @param float $km   Distance [km]
	 * @param int $time   Time [s]
	 * @return string
	 */
	public static function Kmh($km, $time) {
		return number_format($km*3600/$time, 1, ',', '.');
	}

	/**
	 * Display a distance as km or m
	 * @param float $km       Distance [km]
	 * @param int $decimals   Decimals after the point, default: 1
	 * @param bool $track     Run on a tartan track?, default: false
	 */
	public static function Km($km, $decimals = -1, $track = false) {
		if ($km == 0)
			return '';

		if ($track)
			return number_format($km*1000, 0, ',', '.').'m';

		if ($decimals == -1)
			$decimals = CONF_TRAINING_DECIMALS;

		return number_format($km, $decimals, ',', '.').'&nbsp;km';
	}

	/**
	 * Display the time as a formatted string
	 * @uses self::TwoNumbers
	 * @param int $time_in_s
	 * @param bool $show_days	Show days (default) or count hours > 24, default: true
	 * @param bool $show_zeros	Show e.g. '0:00:00' for 0, default: false, can be '2' for 0:00
	 * @return string
	 */
	public static function Time($time_in_s, $show_days = true, $show_zeros = false) {
		if ($time_in_s < 0)
			return '&nbsp;';

		$string    = '';
		$time_in_s = round($time_in_s, 2); // correct float-problem with floor

		if ($show_zeros === true) {
			$string = floor($time_in_s/3600).':'.self::TwoNumbers(floor($time_in_s/60)%60).':'.self::TwoNumbers($time_in_s%60);
			if ($time_in_s - floor($time_in_s) != 0)
				$string .= ','.self::TwoNumbers(round(100*($time_in_s - floor($time_in_s))));
			return $string;
		}

		if ($show_zeros == 2)
			return (floor($time_in_s/60)%60).':'.self::TwoNumbers($time_in_s%60);

		if ($time_in_s < 60)
			return number_format($time_in_s, 2, ',', '.').'s';

		if ($time_in_s >= 86400 && $show_days)
			$string = floor($time_in_s/86400).'d ';

		if ($time_in_s < 3600)
			$string .= (floor($time_in_s/60)%60).':'.self::TwoNumbers($time_in_s%60);
		elseif ($show_days)
			$string .= (floor($time_in_s/3600)%24).':'.self::TwoNumbers(floor($time_in_s/60)%60).':'.self::TwoNumbers($time_in_s%60);
		else
			$string .= floor($time_in_s/3600).':'.self::TwoNumbers(floor($time_in_s/60)%60).':'.self::TwoNumbers($time_in_s%60);

		if ($time_in_s - floor($time_in_s) != 0 && $time_in_s < 3600)
			$string .= ','.self::TwoNumbers(round(100*($time_in_s - floor($time_in_s))));

		return $string;
	}

	/**
	 * Calculate time in seconds from a given string (m:s|h:m:s)
	 * @param string $string
	 * @return int
	 */
	public static function TimeToSeconds($string) {
		$TimeArray = explode(':', $string);

		switch (count($TimeArray)) {
			case 3:
				return ($TimeArray[0]*60 + $TimeArray[1])*60 + $TimeArray[2];
			case 2:
				return $TimeArray[0]*60 + $TimeArray[1];
			default:
				return $string;
		}

		if (count($TimeArray) == 2)
			return $TimeArray[0]*60 + $TimeArray[1];

		return $string;
	}

	/**
	 * Boolean flag: Is this training a competition?
	 * @param int $id
	 */
	public static function TrainingIsCompetition($id) {
		if (!is_numeric($id))
			return false;

		return (Mysql::getInstance()->num('SELECT 1 FROM `'.PREFIX.'training` WHERE `id`='.$id.' AND `typeid`="'.CONF_WK_TYPID.'"') > 0);
	}

	/**
	 * Find the personal best for a given distance
	 * @uses self::Time
	 * @param float $dist       Distance [km]
	 * @param bool $return_time Return as integer, default: false
	 * @return mixed
	 */
	public static function PersonalBest($dist, $return_time = false) {
		$pb = Mysql::getInstance()->fetchSingle('SELECT `s`, `distance` FROM `'.PREFIX.'training` WHERE `typeid`="'.CONF_WK_TYPID.'" AND `distance`="'.$dist.'" ORDER BY `s` ASC');
		if ($return_time)
			return ($pb != '') ? $pb['s'] : 0;
		if ($pb != '')
			return self::Time($pb['s']);
		return '<em>keine</em>';
	}

	/**
	 * Creating a RGB-color for a given stress-value [0-100]
	 * @param int $stress   Stress-value [0-100]
	 */
	public static function Stresscolor($stress) {
		if ($stress > 100)
			$stress = 100;

		$gb = dechex(200 - 2*$stress);

		if ((200 - 2*$stress) < 16)
			$gb = '0'.$gb;

		return 'C8'.$gb.$gb;
	}

	/**
	 * Calculating basic endurance
	 * @uses DAY_IN_S
	 * @param bool $as_int as normal integer, default: false
	 * @param int $timestamp [optional] timestamp
	 */
	public static function BasicEndurance($as_int = false, $timestamp = 0) {
		// TODO: Unittests
		if ($timestamp == 0) {
			if (defined('BASIC_ENDURANCE'))
				return ($as_int) ? BASIC_ENDURANCE : BASIC_ENDURANCE.' &#37;';
			$timestamp = time();
		}

		if (VDOT_FORM == 0)
			return ($as_int) ? 0 : '0 &#37;';

		$diff = Time::diffInDays(START_TIME);
		if ($diff > 182)
			$DaysForWeekKm = 182; // 26 Wochen
		elseif ($diff < 70)
			$DaysForWeekKm = 70;
		else
			$DaysForWeekKm = $diff;

		$DaysForLongjogs        = 70;  // 10 Wochen
		$StartTimeForLongjogs   = $timestamp - $DaysForLongjogs * DAY_IN_S;
		$StartTimeForWeekKm     = $timestamp - $DaysForWeekKm * DAY_IN_S;
		$minKmForLongjog        = 13;
		$TargetWeekKm           = pow(VDOT_FORM, 1.135);
		$TargetLongjogKmPerWeek = log(VDOT_FORM/4) * 12 - $minKmForLongjog;

		$LongjogResult = 0;
		$Longjogs      = Mysql::getInstance()->fetchAsArray('SELECT distance,time FROM '.PREFIX.'training WHERE sportid='.CONF_RUNNINGSPORT.' AND time<='.$timestamp.' AND time>='.$StartTimeForLongjogs.' AND distance>'.$minKmForLongjog.'  ORDER BY time DESC');
		$WeekKmResult  = Mysql::getInstance()->fetchSingle('SELECT SUM(distance) as km FROM '.PREFIX.'training WHERE sportid='.CONF_RUNNINGSPORT.' AND time<='.$timestamp.' AND time>='.$StartTimeForWeekKm);

		foreach ($Longjogs as $Longjog) {
			$Timefactor     = 2 - (2/$DaysForLongjogs) * round ( ($timestamp - $Longjog['time']) / DAY_IN_S , 1 );
			$LongjogResult += $Timefactor * pow( ($Longjog['distance'] - $minKmForLongjog) / $TargetLongjogKmPerWeek, 2 );
		}

		$WeekPercentage    = $WeekKmResult['km'] * 7 / $DaysForWeekKm / $TargetWeekKm;
		$LongjogPercentage = $LongjogResult * 7 / $DaysForLongjogs;
		$Percentage        = round( 100 * ( $WeekPercentage*2/3 + $LongjogPercentage*1/3 ) );

		if ($Percentage < 0)
			$Percentage = 0;
		if ($Percentage > 100)
			$Percentage = 100;

		return ($as_int) ? $Percentage : $Percentage.' &#37;';
	}

	/**
	 * Calculate factor concerning to basic endurance
	 * @param double $distance
	 * @return double
	 */
	static public function VDOTfactorOfBasicEndurance($distance) {
		$BasicEndurance         = self::BasicEndurance(true);
		$RequiredBasicEndurance = pow($distance, 1.23);
		$BasicEnduranceFactor   = 1 - ($RequiredBasicEndurance - $BasicEndurance) / 100;

		if ($BasicEnduranceFactor > 1)
			return 1;
		if ($BasicEnduranceFactor < 0)
			return 0.01;

		return (0.6 + 0.4 * $BasicEnduranceFactor);
	}

	/**
	 * Get prognosis (vdot/seconds) as array
	 * @param double $distance
	 * @param double $VDOT [optional]
	 * @return array
	 */
	static public function PrognosisAsArray($distance, $VDOT = 0) {
		$VDOT  = ($VDOT == 0) ? VDOT_FORM : $VDOT;
		$VDOT *= self::VDOTfactorOfBasicEndurance($distance);
		$PrognosisInSeconds = JD::CompetitionPrognosis($VDOT, $distance);

		return array('vdot' => $VDOT, 'seconds' => $PrognosisInSeconds);
	}

	/**
	 * Get a leading 0 if $int is lower than 10
	 * @param int $int
	 */
	public static function TwoNumbers($int) {
		return ($int < 10) ? '0'.$int : $int;
	}

	/**
	 * Get a special $string if $var is not set
	 * @param mixed $var
	 * @param string $string string to be displayed instead, default: ?
	 */
	public static function Unknown($var, $string = '?') {
		if ($var == NULL || !isset($var))
			return $string;

		if ((is_numeric($var) && $var != 0) || (!is_numeric($var) && $var != '') )
			return $var;

		return $string;
	}

	/**
	 * Cut a string if it is longer than $cut (default CUT_LENGTH)
	 * @uses CUT_LENGTH
	 * @param string $text
	 * @param int $cut [optional]
	 */
	public static function Cut($text, $cut = 0) {
		if ($cut == 0)
			$cut = CUT_LENGTH;

		if (mb_strlen($text) >= $cut)
			return Ajax::tooltip(mb_substr($text, 0, $cut-3).'...', $text);

		return $text;
	}

	/**
	 * Replace every comma with a point
	 * @param string $string
	 */
	public static function CommaToPoint($string) {
		return str_replace(",", ".", $string);
	}

	/**
	 * Is the given array an associative one?
	 * @param array $array
	 * @return bool
	 */
	public static function isAssoc($array) {
		return array_keys($array) !== range(0, count($array) - 1);
	}

	/**
	 * Check the modus of a row from dataset
	 * @param string $row   Name of dataset-row
	 * @return int   Modus
	 */
	public static function getModus($row) {
		$dat = Mysql::getInstance()->fetchSingle('SELECT `name`, `modus` FROM `'.PREFIX.'dataset` WHERE `name`="'.$row.'"');
		return $dat['modus'];
	}

	/**
	 * Get the HFmax from user-table
	 * @return int   HFmax
	 */
	public static function getHFmax() {
		// TODO: Move to class::UserData - possible problem in loading order?
		if (defined('HF_MAX'))
			return HF_MAX;

		$userdata = Mysql::getInstance()->fetchSingle('SELECT `pulse_max` FROM `'.PREFIX.'user` ORDER BY `time` DESC');

		if ($userdata === false) {
			//Error::getInstance()->addWarning('HFmax is not set in database, 200 as default.');
			return 200;
		} elseif ($userdata['pulse_max'] == 0) {
			Error::getInstance()->addWarning('HFmax is 0, taking 200 as default.');
			return 200;
		}

		return $userdata['pulse_max'];
	}

	/**
	 * Get the HFrest from user-table
	 * @return int   HFrest
	 */
	public static function getHFrest() {
		// TODO: Move to class::UserData - possible problem in loading order?
		if (defined('HF_REST'))
			return HF_REST;

		$userdata = Mysql::getInstance()->fetchSingle('SELECT `pulse_rest` FROM `'.PREFIX.'user` ORDER BY `time` DESC');

		if ($userdata === false) {
			//Error::getInstance()->addWarning('HFrest is not set in database, 60 as default.');
			return 60;
		}

		return $userdata['pulse_rest'];
	}

	/**
	 * Get timestamp of first training
	 * @return int   Timestamp
	 */
	public static function getStartTime() {
		$data = Mysql::getInstance()->fetch('SELECT MIN(`time`) as `time` FROM `'.PREFIX.'training`');

		if ($data === false || $data['time'] == 0)
			return time();

		return $data['time'];
	}
}

/**
 * Load a file with simplexml, correcting encoding
 * @param string $filePath
 * @return SimpleXMLElement
 */
function simplexml_load_file_utf8($filePath) {
	return simplexml_load_string_utf8(simplexml_correct_ns(utf8_encode(file_get_contents($filePath))));
}

/**
 * Load a given XML-string with simplexml, correcting encoding
 * @param string $Xml
 * @return SimpleXMLElement
 */
function simplexml_load_string_utf8($Xml) {
	return simplexml_load_string(simplexml_correct_ns($Xml));
}

/**
 * Correct namespace for using xpath in simplexml
 * @param string $string
 * @return string
 */
function simplexml_correct_ns($string) {
	return str_replace('xmlns=', 'ns=', removeBOMfromString($string));
}

/**
 * Remove leading BOM from string
 * @param string $string
 * @return string
 */
function removeBOMfromString($string) {
	return mb_substr($string, mb_strpos($string, "<"));
}

/**
 * Trimmer function for array_walk
 * @param array $value 
 */
function trimValuesForArray(&$value) {
	$value = trim($value);
}