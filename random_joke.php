<?php
/*
Plugin Name: Random Joke
Plugin URI: http://www.gigacart.com/random-joke-widget.html
Description: Widget displays random categorized jokes on your blog. There are over 25,000 jokes in 75 categories. Jokes are saved on gigacart.com database, so you don't need to have space for all the information.
Author: GigaCart
Author URI:http://www.gigacart.com
Version: 1.0.5
*/

require_once(ABSPATH . WPINC . '/rss.php');

if (!defined('MAGPIE_FETCH_TIME_OUT'))
    define('MAGPIE_FETCH_TIME_OUT', 5); // 5 second timeout
if (!defined('MAGPIE_USE_GZIP'))
    define('MAGPIE_USE_GZIP', true);
if (!defined('MAGPIE_DEBUG'))
    define('MAGPIE_DEBUG', false);
/*
 * Class for plugin's widget
 */
class random_jokes_widgets {
    // Path to plugin cache directory
    var $cachePath;
    // Cache file variable
    var $cacheFile;

    /*
     * Class constructor function
     */
    function random_jokes_widgets() {
        $this->cachePath = ABSPATH . 'wp-content/plugins/random-joke/cache/';
        $this->cacheFile = 'random_jokes.cache';
    }

    /*
     * Save data to cache file
     */
    function saveData($data, $widgetId = "1") {
        // Path to cache file
        $pathToFile = sprintf("%s%s-%s.xml", $this->cachePath, $this->cacheFile, $widgetId);
        // Open cache file for writing
        if (!$handle = @fopen($pathToFile, 'w')) {
            echo 'Cannot open file ('.$pathToFile.') Check folder permissions!';
            return false;
        }
        // Write data to cache file
        if (@fwrite($handle, $data) === false) {
            echo 'Cannot write to file ('.$pathToFile.') Check folder permissions!';
            return false;
        }
        // Close cache file
        if (!@fclose($handle)) {
            echo 'Cannot close file ('.$pathToFile.') Check folder permissions!';
            return false;
        }
    }

    function readCache($widgetData, $widgetId = "1") {
        // Get module options
        $jokesoptions = get_option('random_jokes_options');
        // Path to cache file
        $pathToFile = sprintf("%s%s-%s.xml", $this->cachePath, $this->cacheFile, $widgetId);
        // Data variable
        $data = '';
        // Read the data from cache file
        if (!$data = @file_get_contents($pathToFile)) {
            echo 'Cannot read file ('.$pathToFile.') Check folder permissions!';
            return false;
        }

        $RSS = new MagpieRSS($data);
        // if RSS parsed successfully
        if ($RSS) {

            $category = (isset($widgetData['category']))?$widgetData['category']:"all";
            $categoryCount = count(explode(",",$category));
            $outputLines = '';
            foreach($RSS->items as $value) {
                if($categoryCount > 5 || !$widgetData['default_link']) {
                    $linkTitle = $jokesoptions["default_link"]->title;
                    $linkUrl = $jokesoptions["default_link"]->url;                    
                } else {
                    $linkTitle = $widgetData['default_link']->link_title;
                    $linkUrl = $widgetData["default_link"]->link_url;
                }
                // Display joke
                $outputLines .= ltrim($value["description"], "|");
                // Display link only in homepage
                if($value["source"] && is_home()) {
                    $outputLines .= '<br /><small><a href="'.$linkUrl.'">'.$linkTitle.'</a></small>';
                }
            }
            return $outputLines;
        } else {
            $errormsg = 'Failed to parse RSS file.';
            if ($RSS)
                $errormsg .= ' (' . $RSS->ERROR . ')';
            return false;
        }

    }

    function fetchData($widgetData) {
        global $wp_version;
        // Set user specified data
        $category = (isset($widgetData['category']))?$widgetData['category']:"all";
        $wordCount = (isset($widgetData['word_count']))?$widgetData['word_count']:10000;

        if ($wp_version >= '2.7') {
            $client = wp_remote_get('http://www.gigacart.com/development/wp/getJoke.php?category='.$category.'&word_count='.$wordCount);
        } else {
            // Fetch data
            $client = new Snoopy();
            $client->agent = MAGPIE_USER_AGENT;
            $client->read_timeout = MAGPIE_FETCH_TIME_OUT;
            $client->use_gzip = MAGPIE_USE_GZIP;
            @$client->fetch('http://www.gigacart.com/development/wp/getJoke.php?category='.$category.'&word_count='.$wordCount);
        }

        return $client;
    }

    function display($widgetData, $widgetId = "1") {

        global $wp_version;

        $pathToFile = sprintf("%s%s-%s.xml", $this->cachePath, $this->cacheFile, $widgetId);

        $htmlOutput = '';

        // Checking if cache file exist
        if (file_exists($pathToFile) && filesize($pathToFile) > 0) {
            if ($widgetData['cachetime'] > 0)
                $cacheTime = $widgetData['cachetime'];
            else
                $cacheTime = 0;
            // File does exist, so let's check if its expired
            if ((time() - $cacheTime) > filemtime($pathToFile)) {
                // Since cache has expired, let's fetch new data
                $htmlOutput = $this->fetchData($widgetData);

                if ($wp_version >= '2.7') {
                // Before output, let's save new data to cache
                if ( is_wp_error($htmlOutput) )
                    return $htmlOutput->get_error_message();
                elseif ($htmlOutput['response']['code'] == 200)
                   $this->saveData($htmlOutput['body'], $widgetId);
                } else {
                    // Before output, let's save new data to cache
                if ($htmlOutput->status == '200')
                   $this->saveData($htmlOutput->results, $widgetId);
                }

                return $this->readCache($widgetData, $widgetId);
            }
            return $this->readCache($widgetData, $widgetId);
        } else {
            // No file found, someone deleted it or first time widget usage
            // Let's create new file with fresh content
            $htmlOutput = $this->fetchData($widgetData);

            if ($wp_version >= '2.7') {
                // Before output, let's save new data to cache
                if ( is_wp_error($htmlOutput) )
                    return $htmlOutput->get_error_message();
                elseif ($htmlOutput['response']['code'] == 200)
                   $this->saveData($htmlOutput['body'], $widgetId);
            } else {
            // Before output, let's save new data to cache
            if ($htmlOutput->status == '200')
                $this->saveData($htmlOutput->results, $widgetId);
            }
            return $this->readCache($widgetData, $widgetId);
        }
    }

    function init() {

        if (!$options = get_option('widget_random_jokes'))
            $options = array();

        $widget_ops = array('classname' => 'widget_random_jokes', 'description' => 'Display random joke from selected category');
        $control_ops = array('width' => 650, 'height' => 100, 'id_base' => 'random_jokes_widgets');
        $name = 'Random Joke';

        $registered = false;
        foreach (array_keys($options) as $o) {
            if (!isset($options[$o]['title']))
                continue;

            $id = "random_jokes_widgets-$o";

            //check if the widgets is active
            global $wpdb;
            $sql = "SELECT option_value FROM $wpdb->options WHERE option_name = 'sidebars_widgets' AND option_value like '%".$id."%'";
            $var = $wpdb->get_var( $sql );
            //do this to keep the size of the array down
            if (!$var)unset($options[$o]);

            $registered = true;
            wp_register_sidebar_widget($id, $name, array(&$this, 'widget'), $widget_ops, array( 'number' => $o ) );
            wp_register_widget_control($id, $name, array(&$this, 'control'), $control_ops, array( 'number' => $o ) );
        }
        if (!$registered) {
            wp_register_sidebar_widget('random_jokes_widgets-1', $name, array(&$this, 'widget'), $widget_ops, array( 'number' => -1 ) );
            wp_register_widget_control('random_jokes_widgets-1', $name, array(&$this, 'control'), $control_ops, array( 'number' => -1 ) );
        }

        update_option('widget_random_jokes', $options);
    }

    function widget($args, $widget_args = 1) {
        extract($args);

        if (is_numeric($widget_args))
            $widget_args = array('number' => $widget_args);
        $widget_args = wp_parse_args($widget_args, array( 'number' => -1 ));
        extract($widget_args, EXTR_SKIP);
        $options_all = get_option('widget_random_jokes');
        if (!isset($options_all[$number]))
            return;

        $options = $options_all[$number];

        //output the joke(s)
        echo $before_widget.$before_title;
        echo $options["title"];
        echo $after_title;
        echo $this->display($options, $number);
        echo $after_widget;
    }

    function control($widget_args = 1) {

        global $wp_registered_widgets;

        static $updated = false;

        //extract widget arguments
        if ( is_numeric($widget_args) )$widget_args = array('number' => $widget_args);
        $widget_args = wp_parse_args($widget_args, array('number' => -1));
        extract($widget_args, EXTR_SKIP);

        $options_all = get_option('widget_random_jokes');
        if (!is_array($options_all))$options_all = array();  
            
        if (!$updated && !empty($_POST['sidebar'])) {
            $sidebar = (string)$_POST['sidebar'];

            $sidebars_widgets = wp_get_sidebars_widgets();
            if (isset($sidebars_widgets[$sidebar]))
                $this_sidebar =& $sidebars_widgets[$sidebar];
            else
                $this_sidebar = array();

            foreach ($this_sidebar as $_widget_id) {
                if ('widget_random_jokes' == $wp_registered_widgets[$_widget_id]['callback'] && isset($wp_registered_widgets[$_widget_id]['params'][0]['number'])) {
                    $widget_number = $wp_registered_widgets[$_widget_id]['params'][0]['number'];
                    if (!in_array("random_jokes_widgets-$widget_number", $_POST['widget-id']))
                        unset($options_all[$widget_number]);
                }
            }
            foreach ((array)$_POST['widget_random_jokes'] as $widget_number => $posted) {
                if (!isset($posted['title']) && isset($options_all[$widget_number]))
                    continue;
                
                $options = array();
                
                $options['title'] = $posted['title'];
                $options['category'] = implode(",",$posted['category']);
                $options['word_count'] = $posted['word_count'];
                $options['cachetime'] = $posted['cachetime'];
                $options['default_link'] = $this->getLink($options['category']);

                $options_all[$widget_number] = $options;
            }
            update_option('widget_random_jokes', $options_all);
            $updated = true;
        }
		
		$default_options = array(
				'title' => __('Random Joke', 'random-jokes'), 
				'word_count' => 10000,
				'cachetime' => 300
		);

        if (-1 == $number) {
            $number = '%i%';
            $values = $default_options;
        } else {
            $values = $options_all[$number];
        }
        
		// widget options form ?>
        <p align="right"><span class="setting-description"><small><?php _e('all settings are for this widget only.', 'random-jokes')?></small></span></p>
        <p><label><strong><?php _e('Title', 'random-jokes')?></strong></label>
		<input class="widefat" id="widget_random_jokes-<?php echo $number; ?>-title" 
        name="widget_random_jokes[<?php echo $number; ?>][title]" type="text" 
        value="<?php echo htmlspecialchars($values['title'], ENT_QUOTES); ?>" />
        </p>
		<p>
			<label for="widget_random_jokes-<?php echo $number; ?>-category"><?php _e('Select category (ctrl + click to select multiple categories)'); ?></label><br />
			<select id="widget_random_jokes-<?php echo $number; ?>-category" name="widget_random_jokes[<?php echo $number; ?>][category][]" multiple size="5" style="height:auto">
					<? foreach($this->getCategories() as $category) {?>
					<option value="<?php echo $category->cid?>"<?php if (in_array($category->cid, explode(',',$values['category'])) || !($values['category'])) echo ' selected="selected"'; ?>><?php echo $category->name; ?></option>
					<? } ?>
			</select>
		</p>
		<p>
			<label for="widget_random_jokes-<?php echo $number; ?>-word_count">
				<?php _e( 'Joke length limit (in words)' ); ?>
				<input class="widefat" id="widget_random_joke-<?php echo $number; ?>-word_count" name="widget_random_jokes[<?php echo $number; ?>][word_count]" type="text" value="<?php echo $values['word_count']; ?>" />
			</label>
		</p>
		<p>
			<label for="widget_random_jokes-<?php echo $number; ?>-cachetime">
				<?php _e( 'Cache time in seconds' ); ?>
				<input class="widefat" id="widget_random_joke-<?php echo $number; ?>-cachetime" name="widget_random_jokes[<?php echo $number; ?>][cachetime]" type="text" value="<?php echo $values['cachetime']; ?>" />
			</label>
		</p>        

        <?php	
        
	}

    function getCategories() 
    {
        global $wp_version;
        if ($wp_version >= '2.7') {
            $htmlOutput = wp_remote_get('http://www.gigacart.com/development/wp/getJokeCategories.php');
            return json_decode($htmlOutput['body']);
        } else {
            // Fetch data
            $htmlOutput = new Snoopy();
            $htmlOutput->agent = MAGPIE_USER_AGENT;
            $htmlOutput->read_timeout = MAGPIE_FETCH_TIME_OUT;
            $htmlOutput->use_gzip = MAGPIE_USE_GZIP;
            @$htmlOutput->fetch('http://www.gigacart.com/development/wp/getJokeCategories.php');
            return json_decode($htmlOutput->results);
        }
    }

    function getLink($categories) 
    {
        global $wp_version;
        if ($wp_version >= '2.7') {
            $htmlOutput = wp_remote_get('http://www.gigacart.com/development/wp/getDefaultLink.php?categories='.$categories);
            return json_decode($htmlOutput['body']);
        } else {
            // Fetch data
            $htmlOutput = new Snoopy();
            $htmlOutput->agent = MAGPIE_USER_AGENT;
            $htmlOutput->read_timeout = MAGPIE_FETCH_TIME_OUT;
            $htmlOutput->use_gzip = MAGPIE_USE_GZIP;
            @$htmlOutput->fetch('http://www.gigacart.com/development/wp/getDefaultLink.php?categories='.$categories);
            return json_decode($htmlOutput->results);
        }
    }


}

$rjw = new random_jokes_widgets();
add_action('widgets_init', array($rjw, 'init'));


//this adds or REPLACES A VARIABLE into a querystring. 
//Thanks to http://www.addedbytes.com/php/querystring-functions/
function query_strings($url, $key, $value) {
	$url = preg_replace('/(.*)(\?|&)' . $key . '=[^&]+?(&)(.*)/i', '$1$2$4', $url . '&');
	$url = substr($url, 0, -1);
	if (strpos($url, '?') === false) {
		return ($url . '?' . $key . '=' . $value);
	} else {
		return ($url . '&' . $key . '=' . $value);
	}
}

function random_jokes_install() {

	$options["default_link"] = getDefaultLink();
	update_option('random_jokes_options', $options);
}

function getDefaultLink() 
{
    global $wp_version;
    if ($wp_version >= '2.7') {
        $htmlOutput = wp_remote_get('http://www.gigacart.com/development/wp/getDefaultLink.php');
        return json_decode($htmlOutput['body']);
    } else {
            // Fetch data
        $htmlOutput = new Snoopy();
        $htmlOutput->agent = MAGPIE_USER_AGENT;
        $htmlOutput->read_timeout = MAGPIE_FETCH_TIME_OUT;
        $htmlOutput->use_gzip = MAGPIE_USE_GZIP;
        @$htmlOutput->fetch('http://www.gigacart.com/development/wp/getDefaultLink.php');
        return json_decode($htmlOutput->results);
    }
}

function random_jokes_uninstall() {
    delete_option('random_jokes_options');
    delete_option('widget_random_jokes');
}

register_activation_hook(__FILE__, 'random_jokes_install');
register_deactivation_hook(__FILE__, 'random_jokes_uninstall');

?>