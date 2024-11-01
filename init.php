<?php
/*
Plugin Name:  Team Chatz
Plugin URI:   https://wordpress.org/plugins/team-chatz
Description:  A Team Chat Plugin for Your WordPress, so you can stay in touch with other (Administrator, Author, Editor, Shop manager etc)
Version:      2.00
Author:       nath4n
Author URI:   https://profiles.wordpress.org/nath4n
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  wporg
Domain Path:  /languages
*/

// function to create the DB / Options / Defaults					
function team_chatz_options_install() {
    global $wpdb;

    $table_name = $wpdb->prefix . "team_chatz";
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
			`id` INT(3) NOT NULL AUTO_INCREMENT , 
			`chatID` INT(3) NOT NULL , 
			`message` TEXT NOT NULL , 
			`ChTime` DATETIME NOT NULL , 
			PRIMARY KEY (`id`)
          ) $charset_collate; ";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta($sql);
}

// run the install scripts upon plugin activation
register_activation_hook(__FILE__, 'team_chatz_options_install');

add_action('admin_menu', 'team_chatz_modifymenu');

function team_chatz_modifymenu() {
	global $wpdb;
	$table_name = $wpdb->prefix . "team_chatz";
    $wpdb->get_results("SELECT * FROM $table_name");

    $rowcount = $wpdb->num_rows;

	add_menu_page('Team Chatz', //page title
	'Team Chatz ' . '<span class="update-plugins count-6"><span class="plugin-count">'.$rowcount.'</span></span>', //menu title
	'read', //capabilities
	'team_chatz_html', //menu slug
	'team_chatz_html', //function
	'dashicons-format-chat'
	);
}

add_action('admin_enqueue_scripts', 'team_chatz_admin_enqueue', 3);

function team_chatz_admin_enqueue(){
	wp_enqueue_script( 'jquery' );
	wp_register_script('handlebars-min', plugins_url('js/handlebars.min.js', __FILE__), array('jquery'));
	wp_enqueue_script('handlebars-min');
	wp_register_script('list-min', plugins_url('js/list.min.js', __FILE__), array('jquery'));
	wp_enqueue_script('list-min');	
	wp_enqueue_style('team-chatz-style', plugins_url( 'css/team-chatz.min.css' , __FILE__ ));
}

add_action( 'init', 'team_chatz_online_update' );

function team_chatz_online_update() {
    if ( is_user_logged_in()) {
		$user = wp_get_current_user();
		update_user_meta($user->ID, 'online_status', 'online');
	}
}

add_action('wp_logout', 'team_chatz_offline_update');

function team_chatz_offline_update() {
	$user = wp_get_current_user();
	delete_user_meta($user->ID, 'online_status', 'online');
}

add_action( 'wp_ajax_action_clear_chatz', 'action_clear_chatz' );

function action_clear_chatz() {
	global $wpdb;	
    $table_name = $wpdb->prefix . "team_chatz";
    $wpdb->query($wpdb->prepare("DELETE FROM $table_name"));
	wp_die();
}

add_action( 'wp_ajax_action_save_chatz', 'action_save_chatz' );

function action_save_chatz() {
	global $wpdb;
	$chatID = intval( sanitize_text_field($_POST["chatID"]) );
    $message = sanitize_text_field($_POST["message"]);
    // insert
    $table_name = $wpdb->prefix . "team_chatz";
    $wpdb->insert(
        $table_name, //table
        array('chatID' => $chatID, 'message' => $message, 'ChTime' => date('Y-m-d H:i:s')), //data
        array('%d', '%s', '%s') //data format			
    );	
	wp_die();
}

add_action( 'wp_ajax_action_load_chatz', 'action_load_chatz' );

function action_load_chatz() {
	global $wpdb;	
	$current_user = wp_get_current_user();	
    $table_name = $wpdb->prefix . "team_chatz";
    $rows = $wpdb->get_results("SELECT * from $table_name ORDER BY id ASC");
	foreach ($rows as $row) {
		if ($row->chatID !== '666') {
			$user = get_user_by('id', $row->chatID);
			$userLogin = $user->user_login;
		}
		$newDateTime = date('h:i A', strtotime($row->ChTime));
		if ($row->chatID == $current_user->ID) {
			$msgHtml .= "<li class='clearfix'>
					<div class='message-data align-right'>
						<span class='message-data-time' >".$newDateTime.", ".team_chatz_getTheDay($row->ChTime)."</span> &nbsp; &nbsp;
						<span class='message-data-name' >".$userLogin."</span> <i class='dashicons dashicons-marker me'></i>
					</div>
				<div class='message my-message float-right'>".$row->message."</div>
				</li>";
		} elseif ($row->chatID == '666') {
			$msgHtml .= "<li>
				<div class='message-data'>
					<span class='message-data-name'><i class='dashicons dashicons-marker system'></i>System</span>
					<span class='message-data-time'>".$newDateTime.", ".team_chatz_getTheDay($row->ChTime)."</span>
				</div>
			<div class='message system-message'>".$row->message."</div>
			</li>";
		} else {
			$msgHtml .= "<li>
				<div class='message-data'>
					<span class='message-data-name'><i class='dashicons dashicons-marker other'></i>".$userLogin."</span>
					<span class='message-data-time'>".$newDateTime.", ".team_chatz_getTheDay($row->ChTime)."</span>
				</div>
			<div class='message other-message'>".$row->message."</div>
			</li>";
		}
	}
	echo $msgHtml;
	wp_die();
}

function team_chatz_getTheDay($date) {
	$curr_date=strtotime(date("Y-m-d H:i:s"));
	$the_date=strtotime($date);
	$diff=floor(($curr_date-$the_date)/(60*60*24));
	switch($diff) {
		case 0:
			return "Today";
			break;
		case 1:
			return "Yesterday";
			break;
		default:
			return $diff." Days ago";
	}
}

function team_chatz_html() {
	global $wpdb;
	$table_name = $wpdb->prefix . "team_chatz";
    $wpdb->get_results("SELECT * FROM $table_name");

    $rowcount = $wpdb->num_rows;
	$current_user = wp_get_current_user();
	
	include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
	  $wooStatus = "WooCommerce is active";
	} else {
	  $wooStatus = "WooCommerce is not active";
	}
	
	// count posts
	$count_posts = wp_count_posts();
	$published_posts = $count_posts->publish;
	// count pages
	$count_pages = wp_count_posts('page');
	$published_pages = $count_pages->publish;
	// count comments
	$count_comments = wp_count_comments();
	$approved_comments = $count_comments->approved;
	
?>
<div class="notice notice-info is-dismissible">
	<p><strong><u>Team Chatz - Bot (System)</u> :</strong></p>
	<p><code>'hi', 'hi all', 'hi system', 'hi everybody', 'hello', 'hello system'</code> (Say Hello to System)</p>
	<p><code>'time', 'current time', 'now'</code> (Ask System.. what time it is now?)</p>
	<p><code>'woo', 'woocommerce'</code> (Ask System.. is WooCommerce active?)</p>
	<p><code>'post', 'posts'</code> (Ask System.. the Published Posts)</p>
	<p><code>'page', 'pages'</code> (Ask System.. the Published Pages)</p>
	<p><code>'comment', 'comments'</code> (Ask System.. the Approved Comments)</p>
	<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
</div>
<div class="container clearfix" style="margin-top: 40px;">
	<div class="people-list" id="people-list">
		<div class="search">
			<input type="text" placeholder="search.." />
			<i class="fa fa-search"></i>
		</div>
		<ul class="list">
			<?php
			$users = get_users( 'blog_id=1' );
			$args = array(
			   'class' => 'img-rounded'
			 );
			foreach ($users as $user) {

				$lastLogin = get_user_meta($user->ID, 'online_status', true);
				// this will list every user and whether they logged in within the last 30 minutes
				if (!empty($lastLogin)) {
					echo '<li class="clearfix">'.get_avatar( $user->ID, 55, '', 'avatar', $args).'
						<div class="about">
							<div class="name">'.esc_html( $user->user_login ).'</div>
							<div class="status">
								<i class="dashicons dashicons-marker online"></i> Online
							</div>
						</div>
					</li>';
				} else { 
					echo '<li class="clearfix">'.get_avatar( $user->ID, 55, '', 'avatar', $args).'
						<div class="about">
							<div class="name">'.esc_html( $user->user_login ).'</div>
							<div class="status">
								<i class="dashicons dashicons-marker offline"></i> Offline
							</div>
						</div>
					</li>';
				}

			}
			?>
		</ul>
	</div>   
    <div class="chat">
		<div class="chat-header clearfix">
			<div class="fullscreen"></div>
			<div class="chat-about">
				<div class="chat-with"><?php echo get_bloginfo( 'name' ); ?> - Team Chatz</div>
				<div class="chat-num-messages">Already <?php echo $rowcount; ?> Messages</div>
			</div>
			<i class="dashicons dashicons-wordpress"></i>
		</div>
		<div class="chat-history">
			<ul>
				<!-- Chat History Should be here.. -->
			</ul>
		</div>      
		<div class="chat-message clearfix">
			<textarea name="message-to-send" id="message-to-send" placeholder ="Type your message.." rows="3"></textarea>			
			<?php 
			if ( current_user_can( 'administrator' ) ) {
				?>
				<a href="#" id="clearChatz" onclick="clearChatz()"><i class="dashicons dashicons-trash"></i></a> <strong><i>(Admin Only)</i></strong>
				<?php
			}
			?>
			<button>Send</button>
		</div>
    </div>
</div>
<script>
(function(){  
	var chat = {
		chatID: '<?php echo $current_user->ID; ?>',
		userLogin: '<?php echo $current_user->user_login; ?>',
		messageToSend: '',
		messageResponses: [
		  'Hello ',
		  '<?php echo $wooStatus; ?>',
		  'Published Posts : <?php echo $published_posts; ?>',
		  'Published Pages : <?php echo $published_pages; ?>',
		  'Approved Comments : <?php echo $approved_comments; ?>'
		],
		init: function() {
		  this.cacheDOM();
		  this.bindEvents();
		  this.render();
		},
		cacheDOM: function() {
		  this.$chatHistory = jQuery('.chat-history');
		  this.$button = jQuery('button');
		  this.$textarea = jQuery('#message-to-send');
		  this.$chatHistoryList =  this.$chatHistory.find('ul');
		},
		bindEvents: function() {
		  this.$button.on('click', this.addMessage.bind(this));
		  this.$textarea.on('keyup', this.addMessageEnter.bind(this));
		},		
		render: function() {
		  this.scrollToBottom();
		  if (this.messageToSend.trim() !== '') {
			var contextData = { 
			  'action': 'action_save_chatz',
			  'chatID': this.chatID,
			  'message': this.messageToSend
			};
			jQuery.post(ajaxurl, contextData);
			autoLoad.init();
			this.$textarea.val('');			
			// responses
			if (['time', 'current time', 'now'].indexOf(this.messageToSend.toLowerCase().trim()) >= 0 ) {
				var contextResponse = { 
				  'action': 'action_save_chatz',
				  'chatID': '666',
				  'message': this.getCurrentTime()
				};
			} else if (['hi', 'hi all', 'hi system', 'hi everybody', 'hello', 'hello system'].indexOf(this.messageToSend.toLowerCase().trim()) >= 0 ) {
				var contextResponse = { 
				  'action': 'action_save_chatz',
				  'chatID': '666',
				  'message': this.messageResponses[0]+this.userLogin
				};
			} else if (['woo', 'woocommerce'].indexOf(this.messageToSend.toLowerCase().trim()) >= 0 ) {
				var contextResponse = { 
				  'action': 'action_save_chatz',
				  'chatID': '666',
				  'message': this.messageResponses[1]
				};
			} else if (['post', 'posts'].indexOf(this.messageToSend.toLowerCase().trim()) >= 0 ) {
				var contextResponse = { 
				  'action': 'action_save_chatz',
				  'chatID': '666',
				  'message': this.messageResponses[2]
				};
			} else if (['page', 'pages'].indexOf(this.messageToSend.toLowerCase().trim()) >= 0 ) {
				var contextResponse = { 
				  'action': 'action_save_chatz',
				  'chatID': '666',
				  'message': this.messageResponses[3]
				};
			} else if (['comment', 'comments'].indexOf(this.messageToSend.toLowerCase().trim()) >= 0 ) {
				var contextResponse = { 
				  'action': 'action_save_chatz',
				  'chatID': '666',
				  'message': this.messageResponses[4]
				};
			}
			
			setTimeout(function() {
			  if (contextResponse !== null) {
					jQuery.post(ajaxurl, contextResponse);
					autoLoad.init();
			  }
			}.bind(this), 1500);
			
		  }		  
		},
		addMessage: function() {
		  this.messageToSend = this.$textarea.val()
		  this.render();
		},
		addMessageEnter: function(event) {
			// enter was pressed
			if (event.keyCode === 13) {
			  this.addMessage();
			}
		},
		scrollToBottom: function() {
		   this.$chatHistory.scrollTop(this.$chatHistory[0].scrollHeight);
		},
		getCurrentTime: function() {
		  return new Date().toLocaleTimeString().
				  replace(/([\d]+:[\d]{2})(:[\d]{2})(.*)/, "$1$3");
		},
		getRandomItem: function(arr) {
		  return arr[Math.floor(Math.random()*arr.length)];
		}		
	};		
		
	chat.init();
	
	var searchFilter = {
		options: { valueNames: ['name'] },
		init: function() {
		  var userList = new List('people-list', this.options);
		  var noItems = jQuery('<li id="no-items-found">No items found</li>');
		  
		  userList.on('updated', function(list) {
			if (list.matchingItems.length === 0) {
			  jQuery(list.list).append(noItems);
			} else {
			  noItems.detach();
			}
		  });
		}
	};
  
	searchFilter.init();
	
	var autoLoad = {
		init: function() {
		  var data = {
			'action': 'action_load_chatz'
		  };

		  // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
		  jQuery.post(ajaxurl, data, function(html) {			
			jQuery('.chat-history').find('ul').html(html); //Insert chat log into the .chat-history div
			jQuery('.chat-history').scrollTop(jQuery('.chat-history')[0].scrollHeight);
		  });
		}
	};
	
	setInterval(autoLoad.init,1500);
	
	jQuery("#clearChatz").click(function(){
		if(confirm("Are you sure you want remove the Chatz?")){
			var clearData = { 
			'action': 'action_clear_chatz'
			};
			jQuery.post(ajaxurl, clearData);
			autoLoad.init();
		}
		else{
			return false;
		}    
	});
})();
</script>
<?php
}
?>