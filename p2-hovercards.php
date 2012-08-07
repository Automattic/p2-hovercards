<?php /*

**************************************************************************

Plugin Name:  P2 Hovercards
Plugin URI:   https://github.com/Automattic/p2-hovercards
Description:  API for adding hovercards on things like Zendesk, Trac, etc
Version:      1.0
Author:       Automattic
Author URI:   http://automattic.com/
License:      GPLv2 or later

Text Domain:  p2-hovercards
Domain Path:  /languages/

**************************************************************************/

/**
 * Relies on the front-end ajax support of P2.
 *
 * New services can be added with `p2_hovercards_add_service()`.
 * It takes the name of the serivce, the regex pattern to find its tags,
 * the regex replacement for a url and a ticket, and a callback for setting
 * up data for the card.
 * 
 * You get an 'id', 'service', and 'url'. The callback should return an
 * array with 'title', 'subtitle', 'url', 'description', 'comments',
 * 'meta', where 'comments' and 'meta' are arrays.
 *
 * Example:
	
	p2_hovercards_add_service( 'zendesk', '#(\d+)-z', '<a href="http://$2.trac.wordpress.org/ticket/$1">$0</a>', '$1', function( $args ){
		$id = (int) $args[ 'id' ];
		$url = $args[ 'url' ];
		$service = $args[ 'service' ];

		// Do stuff with $id, $service, and $url
		
		return compact( 'title', 'subtitle', 'url', 'description', 'comments', 'meta' );
	});

 */

class P2_hovercards {

	const VERSION = 1.0;

	public $regex = array();
	public $services = array();
	public $tickets = array();
	public $urls = array();

	function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Set up the filters to modify the four arrays
	 */
	function init() {
		if( ! class_exists( 'P2' ) )
			return;
		
		$this->regex = apply_filters( 'p2_hovercards_regex_keys', $this->regex );
		$this->services = apply_filters( 'p2_hovercards_regex_services', $this->services );
		$this->tickets = apply_filters( 'p2_hovercards_regex_tickets', $this->tickets );
		$this->urls = apply_filters( 'p2_hovercards_regex_urls', $this->urls );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'p2_ajax',            array( $this, 'handle_ajax' ) );

		// Excerpt content and make all links work similar to make_clickable
		add_filter( 'p2_hovercards_excerpt', array( $this, 'excerpt' ), 10, 2 );

		// hovercardify the stuff
		add_filter( 'the_content',  array( $this, 'markup_links' ), -10, 1 );
		add_filter( 'comment_text', array( $this, 'markup_links' ), -10, 1 );
	}

	/**
	 * Set up the javascript for ajax loading of cards and the stylesheet
	 */
	function enqueue_scripts() {
		wp_register_script( 'jquery.sonar', plugins_url( '/js/jquery.sonar.js', __FILE__ ), array( 'jquery' ), '3.0' );
		wp_register_script( 'spin', plugins_url( '/js/spin.js', __FILE__ ), array(), '1.2.5' );
		wp_register_script( 'p2-hovercards', plugins_url( '/js/p2-hovercards.js', __FILE__ ), array( 'jquery', 'jquery.sonar', 'spin' ), self::VERSION );
		wp_register_style( 'p2-hovercards', plugins_url( '/css/p2-hovercards.css', __FILE__ ), false, self::VERSION );
		
		wp_enqueue_script( 'p2-hovercards' );
		wp_enqueue_style( 'p2-hovercards' );
	}

	/**
	 * Handle ajax requests and decide what to do with them
	 */
	function handle_ajax( $action ) {
		if ( 'load_p2_hovercards' == $action )
			$this->load_p2_hovercards();
	}

	/**
	 * Set up a hovercard to be printed with ajax
	 */
	function load_p2_hovercards() {
		// If there's no info to be had, bail
		if( !$this->get_hovercard_info() )
			exit;

		$defaults = array(
			'title' => '',
			'url' => '',
			'subtitle' => '',
			'description' => '',
			'comments' => array(),
			'meta' => array()
		);

		$hovercard_info = wp_parse_args( $this->get_hovercard_info(), $defaults );

		// Print the card
		print "<div class='p2-hovercard'>";

		print "<div class='content'>";
		if ( '' != $hovercard_info['url'] ) {
			printf( "<h4 class='title'><a href='%s'>%s</a></h4>", $hovercard_info['url'], $hovercard_info['title'] );
		} else {
			printf( "<h4 class='title'>%s</h4>", $hovercard_info['title'] );
		}
		printf( "<span class='subtitle'>%s</span>", $hovercard_info['subtitle'] );
		printf( "<div class='description'>%s</div>", $hovercard_info['description'] );
		print "</div>";

		if ( count( $hovercard_info['comments'] ) > 0 ) {
			print "<div class='comments'>";
			printf( "<h5>Last %d Comments</h5>", count( $hovercard_info['comments'] ) );
			print "<ul>";
			foreach ( $hovercard_info['comments'] as $hovercard_info['comment'] ) {
				print "<li class='comment'>";
				print "<div class='comment-meta'>";
				if ( isset( $hovercard_info['comment']['author'] ) )
					printf( "<span class='author'>%s</span>", $hovercard_info['comment']['author'] );
				if ( isset( $hovercard_info['comment']['date'] ) )
					printf( " &mdash; <span class='date'>%s</span>", $hovercard_info['comment']['date'] );
				print '</div>';
				printf( "<div class='comment-value'>%s</div>", $hovercard_info['comment']['comment'] );
				print "</li>";
			}
			print "</ul>";
			print "</div>";
		}

		print "<div class='meta'>";
		foreach ( $hovercard_info['meta'] as $key => $value ) {
			printf( "<span class='single-meta'>%s: %s</span>", $key, $value );
		}
		print "</div>";

		print "</div>";
		exit;
	}

	/**
	 * Grab the tag, process it with regex to find
	 * the ID and tag and return processed
	 * data as specified by the API.
	 */
	function get_hovercard_info() {
		$tag = esc_attr( $_REQUEST[ 'slug' ] );
		$id = $this->service_regex( $tag, $this->tickets );
		$url = $this->service_regex( $tag, $this->urls );
		$service = $this->service_regex( $tag, $this->services );

		return apply_filters( 'p2_hovercards_' . $service, compact( 'id', 'service', 'url' ) );
	}

	/**
	 * Parse content with the regex array and return
	 * a value depending on what's in the `$match` array
	 *
	 * @param string $content The content to be parsed
	 * @param array $match The possible results to be matched against
	 */
	function service_regex( $content, $match ) {
		$find = $this->regex;
		
		preg_match_all( '#[^>]+(?=<[^/]*[^a])|[^>]+$#', $content, $matches, PREG_SET_ORDER );

		foreach ( $matches as $val )
			$content = preg_replace( array_map( array( $this, 'markup_link_regex_map' ), $find ), $match, $val[0] );
	
		return $content;
	}

	/**
	 * Limit $content to $length characters while keeping all links active
	 *
	 * In order to keep all visible links active, make a new string and run it through
	 * `make_clickable`, grab an array of all the links with `preg_match_all`,
	 * shorten the string to size = $length, and replace anything that looks like
	 * a URL with the links in the array of URLs.
	 * 
	 * @param string $content The content to excerpt
	 * @param int $length The length of the content in characters
	 */
	function excerpt( $content, $length = 250 ) {
		$linked = make_clickable( $content );

		// if it's already short enough, we're done
		if ( strlen( $content ) < $length )
			return $linked;

		// grab an array of all the anchor tags, then trim it and check
		// for things that look like URLs
		preg_match_all( '#<a\s+.*?href=[\'"]([^\'"]+)[\'"]\s*(?:title=[\'"]([^\'"]+)[\'"])?.*?>((?:(?!</a>).)*)</a>#i', $linked, $urls);
		$content = substr( $content, 0, $length );
		$url_clickable = '~
			([\\s(<.,;:!?])                                        # 1: Leading whitespace, or punctuation
			(                                                      # 2: URL
				[\\w]{1,20}+://                                # Scheme and hier-part prefix
				(?=\S{1,2000}\s)                               # Limit to URLs less than about 2000 characters long
				[\\w\\x80-\\xff#%\\~/@\\[\\]*(+=&$-]*+         # Non-punctuation URL character
				(?:                                            # Unroll the Loop: Only allow puctuation URL character if followed by a non-punctuation URL character
					[\'.,;:!?)]                            # Punctuation URL character
					[\\w\\x80-\\xff#%\\~/@\\[\\]*(+=&$-]++ # Non-punctuation URL character
				)*
			)
			(\)?)                                                  # 3: Trailing closing parenthesis (for parethesis balancing post processing)
		~xS'; // The regex is a non-anchored pattern and does not have a single fixed starting character.
		      // Tell PCRE to spend more time optimizing since, when used on a page load, it will probably be used several times.
		preg_match_all( $url_clickable, $content, $matches );

		// set up the anchors with the trimmed text, but the pre-trimmed href
		$replace = array();
		for( $i = 0; $i < count( $matches[2] ); $i++ )
			$replace[] = sprintf( "<a href='%s' rel='nofollow'>%s</a>", $urls[1][$i], $matches[2][$i] );

		// replace anything that looks like a URL with the next anchor in $replace
		$content = str_replace( $matches[2], $replace,  $content );

		return force_balance_tags( $content . ' [&hellip;]' );
	}

	function markup_links( $content ) {
		global $wpdb;

		$find = $this->regex;
		$replace = array_map( array( $this, 'p2_hovercardify' ), $this->urls );

		preg_match_all( '#[^>]+(?=<[^/]*[^a])|[^>]+$#', $content, $matches, PREG_SET_ORDER );

		foreach ( $matches as $val ) {
			$content = str_replace( $val[0], preg_replace( array_map( array( $this, 'markup_link_regex_map' ), $find ), $replace, $val[0] ), $content );
		}

		return $content;
	}

	function markup_link_regex_map( $regex ) {
		return "/(?<![\\w-])$regex(?![\\w-])/";
	}

	function p2_hovercardify( $content ) {
		return '<span class="p2_hovercardify">' . $content . '</span>';
	}

}

new p2_hovercards();

/**
 * Helper function used to add a new service.
 *
 * @param string $service The name of the service being added
 * @param string $regex The regex pattern used to find the tag for this service
 * @param string $url The regex replacement to get the URL of the ticket
 * @param string $ticket The regex replacement to get a ticket ID from the tag
 * @param callback $callback The function used to process data before being displayed in the hovercard
 */
function p2_hovercards_add_service( $service, $regex, $url, $ticket, $callback ) {
	add_filter( 'p2_hovercards_regex_keys', function( $r ) use ( $regex ) {
		$r[] = $regex;
		return $r;
	});
	add_filter( 'p2_hovercards_regex_services', function( $r ) use ( $service ) {
		$r[] = $service;
		return $r;
	});
	add_filter( 'p2_hovercards_regex_urls', function( $r ) use ( $url ) {
		$r[] = $url;
		return $r;
	});
	add_filter( 'p2_hovercards_regex_tickets', function( $r ) use ( $ticket ) {
		$r[] = $ticket;
		return $r;
	});
	add_filter( 'p2_hovercards_' . $service, $callback );
}
