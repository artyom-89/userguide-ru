<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Custom Markdown parser for Kohana documentation.
 *
 * @package    Kohana/Userguide
 * @category   Base
 * @author     Kohana Team
 * @copyright  (c) 2009 Kohana Team
 * @license    http://kohanaphp.com/license
 */
class Kohana_Kodoc_Markdown extends MarkdownExtra_Parser {

	/**
	 * @var  string  base url for links
	 */
	public static $base_url = '';

	/**
	 * @var  string  base url for images
	 */
	public static $image_url = '';
	
	/**
	 * Currently defined heading ids.  
	 * Used to prevent creating multiple headings with same id.
	 * @var array
	 */
	protected $_heading_ids = array();
	
	public function __construct()
	{
		// doImage is 10, add image url just before
		$this->span_gamut['doImageURL'] = 9;

		// doLink is 20, add base url just before
		$this->span_gamut['doBaseURL'] = 19;

		// Add API links
		$this->span_gamut['doAPI'] = 90;

		// Add note spans last
		$this->span_gamut['doNotes'] = 100;

		// Parse Kohana view inclusions at the very end
		$this->document_gamut['doIncludeViews'] = 100;

		// PHP4 makes me sad.
		parent::MarkdownExtra_Parser();
	}
	
	function _doHeaders_callback_setext($matches) 
	{
		# Terrible hack to check we haven't found an empty list item.
		if ($matches[2] == '-' && preg_match('{^-(?: |$)}', $matches[1]))
			return $matches[0];
		
		$level = $matches[2]{0} == '=' ? 1 : 2;
		$block = '<h'.$level.' id="'.$this->make_heading_id($matches[2]).'">'
					.$this->runSpanGamut($matches[1])
					.'</h'.$level.'>';
		return "\n" . $this->hashBlock($block) . "\n\n";
	}
	
	function _doHeaders_callback_atx($matches) 
	{
		$level = strlen($matches[1]);
		$block = '<h'.$level.' id="'.$this->make_heading_id($matches[2]).'">'
					.$this->runSpanGamut($matches[2])
					.'</h'.$level.'>';
		return "\n" . $this->hashBlock($block) . "\n\n";
	}
	
	/**
	 * Makes a heading id from the heading text
	 * If any heading share the same name then subsequent headings will have an integer appended
	 *
	 * @param  string The heading text
	 * @return string ID for the heading
	 */
	function make_heading_id($heading)
	{
		$id = url::title($heading, '-', TRUE);
		
		if(isset($this->_heading_ids[$id]))
		{
			$id .= '-';
			
			$count = 0;
			
			while (isset($this->_heading_ids[$id]) AND ++$count)
			{
				$id .= $count;
			}
		}		
		
		return $id;
	}

	public function doIncludeViews($text)
	{
		if (preg_match_all('/{{([^\s{}]++)}}/', $text, $matches, PREG_SET_ORDER))
		{
			$replace = array();

			foreach ($matches as $set)
			{
				list($search, $view) = $set;

				try
				{
					$replace[$search] = View::factory($view)->render();
				}
				catch (Exception $e)
				{
					ob_start();

					// Capture the exception handler output and insert it instead
					Kohana::exception_handler($e);

					$replace[$search] = ob_get_clean();
				}
			}

			$text = strtr($text, $replace);
		}

		return $text;
	}

	/**
	 * Add the current base url to all local links.
	 *
	 *     [filesystem](about.filesystem "Optional title")
	 *
	 * @param   string  span text
	 * @return  string
	 */
	public function doBaseURL($text)
	{
		// URLs containing "://" are left untouched
		return preg_replace('~(?<!!)(\[.+?\]\()(?!\w++://)(?!#)(\S*(?:\s*+".+?")?\))~', '$1'.Kodoc_Markdown::$base_url.'$2', $text);
	}

	/**
	 * Add the current base url to all local images.
	 *
	 *     ![Install Page](img/install.png "Optional title")
	 *
	 * @param   string  span text
	 * @return  string
	 */
	public function doImageURL($text)
	{
		// URLs containing "://" are left untouched
		return preg_replace('~(!\[.+?\]\()(?!\w++://)(\S*(?:\s*+".+?")?\))~', '$1'.Kodoc_Markdown::$image_url.'$2', $text);
	}

	/**
	 * Parses links to the API browser.
	 *
	 *     [Class_Name] or [Class::$property]
	 *
	 * @param   string   span text
	 * @return  string
	 */
	public function doAPI($text)
	{
		return preg_replace_callback('/\[([a-z_]++(?:::\$?[a-z_]++)?)\]/i', array($this, '_convert_api_link'), $text);
	}

	public function _convert_api_link($matches)
	{
		static $route;

		if ($route === NULL)
		{
			$route = Route::get('docs/api');
		}

		$link = $matches[1];

		if (strpos($link, '::'))
		{
			// Split the class and method
			list($class, $method) = explode('::', $link, 2);

			if ($method[0] === '$')
			{
				// Class property, not method
				$method = 'property:'.substr($method, 1);
			}

			// Add the id symbol to the method
			$method = '#'.$method;
		}
		else
		{
			// Class with no method
			$class  = $link;
			$method = NULL;
		}

		return HTML::anchor($route->uri(array('class' => $class)).$method, $link);
	}

	/**
	 * Wrap notes in the applicable markup. Notes can contain single newlines.
	 *
	 *     [!!] Remember the milk!
	 *
	 * @param   string  span text
	 * @return  string
	 */
	public function doNotes($text)
	{
		if ( ! preg_match('/^\[!!\]\s*+(.+?)(?=\n{2,}|$)/s', $text, $match))
		{
			return $text;
		}

		return $this->hashBlock('<p class="note">'.$match[1].'</p>');
	}

} // End Kodoc_Markdown
