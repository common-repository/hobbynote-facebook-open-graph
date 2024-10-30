<?php 
/*
Plugin Name: Hobbynote Facebook Open Graph
Plugin URI: http://www.hobbynote.com
Description: Aider les utilisateurs à implémenter et personnaliser les balises Open Graph facilement.
Author: Hobbynote
Author URI: http://www.hobbynote.com
Version: 1.0
License: GPL2++
*/

// Vérification du dossier d'installation Wordpress
defined('ABSPATH') or die('Impossible de charger le dossier wordpress');

// Recherche de la version du plugin
function hn_fbog_plugin_get_version() {
	if (!function_exists('get_plugins')) require_once (ABSPATH . 'wp-admin/includes/plugin.php');
	$plugin_folder = get_plugins('/' . plugin_basename(dirname(__FILE__)));
	$plugin_file = basename((__FILE__));
	return $plugin_folder[$plugin_file]['Version'];
}

// Ajout du prefix Open Graph à la balise <html>
function hn_fbog_namespace($output) {
	return $output.' prefix="og: http://ogp.me/ns#"';
}

// Ajout du filtre language
add_filter('language_attributes','hn_fbog_namespace');

// Fonction de recherche des images 
function hn_fbog_find_images() {
	global $post, $posts;
	$content = $post->post_content;
	$output = preg_match_all( '/<img.+src=[\'"]([^\'"]+)[\'"].*>/i', $content, $matches );
	if ( $output === FALSE ) {
		return false;
	}
	$hn_fbog_images = array();
	foreach ( $matches[1] as $match ) {
		if ( ! preg_match('/^https?:\/\//', $match ) ) {
			$match = site_url( '/' ) . ltrim( $match, '/' );
		}
		$hn_fbog_images[] = $match;
	}
	return $hn_fbog_images;
}

// Fonction de lancement de retour du contenu
function hn_fbog_start_ob() {
	if ( ! is_feed() ) {
		ob_start( 'hn_fbog_callback' );
	}
}

// Fonction de retour du contenu
function hn_fbog_callback( $content ) {
	$title = preg_match( '/<title>(.*)<\/title>/', $content, $title_matches );
	$description = preg_match( '/<meta name="description" content="(.*)"/', $content, $description_matches );
	if ( $title !== FALSE && count( $title_matches ) == 2 ) {
		$content = preg_replace( '/<meta property="og:title" content="(.*)">/', '<meta property="og:title" content="' . $title_matches[1] . '">', $content );
	}
	if ( $description !== FALSE && count( $description_matches ) == 2 ) {
		$content = preg_replace( '/<meta property="og:description" content="(.*)">/', '<meta property="og:description" content="' . $description_matches[1] . '">', $content );
	}
	return $content;
}

// Fonction d'envoi des données tampons
function hn_fbog_flush_ob() {
	if ( ! is_feed() ) {
		ob_end_flush();
	}
}

// Intialisation des retours
add_action( 'init', 'hn_fbog_start_ob', 0 );
add_action( 'wp_footer', 'hn_fbog_flush_ob', 10000 ); 

// Fonction d'ajout des balises meta 
function hn_fbog_build_head() {
	global $post;
	$options = get_option('hn_fbog');
	if ( ( !isset( $options['hn_fbog_admin_ids'] ) || empty( $options['hn_fbog_admin_ids'] ) ) && ( !isset( $options['hn_fbog_app_id'] ) || empty( $options['hn_fbog_app_id'] ) ) ) {
		echo "\n<!-- Hobbynote Facebook Open Graph - APP ID ou Admin ID manquant -->\n";
	} else {
		echo "\n<!-- Hobbynote Facebook Open Graph ".hn_fbog_plugin_get_version()." -->\n";
		if ( isset( $options['hn_fbog_admin_ids'] ) && !empty( $options['hn_fbog_admin_ids'] ) ) {
			echo '<meta property="fb:admins" content="' . esc_attr( apply_filters( 'hn_fbog_app_id', $options['hn_fbog_admin_ids'] ) ) . '"/>' . "\n";
		}
		if ( isset( $options['hn_fbog_app_id'] ) && !empty( $options['hn_fbog_app_id'] ) ) {
			echo '<meta property="fb:app_id" content="' . esc_attr( apply_filters( 'hn_fbog_app_id', $options['hn_fbog_app_id'] ) ) . '"/>' . "\n";
		}
		if (is_home() || is_front_page() ) {
			$hn_fbog_url = get_bloginfo( 'url' );
		} else {
			$hn_fbog_url = 'http' . (is_ssl() ? 's' : '') . "://".$_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		}
		echo '<meta property="og:url" content="' . esc_url( apply_filters( 'hn_fbog_url', $hn_fbog_url ) ) . '"/>' . "\n";
		if (is_home() || is_front_page() ) {
			$hn_fbog_title = get_bloginfo( 'name' );
		} else {
			$hn_fbog_title = get_the_title();
		}
		if ( is_singular() ) {
			if ( has_excerpt( $post->ID ) ) {
				$hn_fbog_description = strip_tags( get_the_excerpt( $post->ID ) );
			} else {
				$hn_fbog_description = str_replace( "\r\n", ' ' , substr( strip_tags( strip_shortcodes( $post->post_content ) ), 0, 160 ) );
			}
		} else {
			$hn_fbog_description = get_bloginfo( 'description' );
		}
		if (class_exists('WPSEO_Frontend')) { 
			$object = new WPSEO_Frontend();
			if ($options['hn_fbog_seotitle'] == 'yes' && $object->title(false)) {
				$seoTitle = $object->title(false);
			} else {
				$seoTitle = the_title_attribute(array(
					'echo' => false
				));
			}
			if ($options['hn_fbog_seodescription'] == 'yes' && $object->metadesc(false)) {
				$seoDescription = $object->metadesc(false);
			} else {
				$seoDescription = apply_filters('hn_fbog_get_excerpt', get_excerpt_by_id($post->ID) );
			}
		} elseif (class_exists('All_in_One_SEO_Pack')) {
			global $post;
			$post_id = $post;
			if (is_object($post_id)) $post_id = $post_id->ID;
			if ($options['hn_fbog_seotitle'] == 'yes' && get_post_meta(get_the_ID() , '_aioseop_title', true)) {
				$seoTitle = htmlspecialchars(stripcslashes(get_post_meta($post_id, '_aioseop_title', true)));
			} else {
				$seoTitle = the_title_attribute(array(
					'echo' => false
				));
			}
			if ($options['hn_fbog_description'] == 'yes' && get_post_meta(get_the_ID() , '_aioseop_description', true)) {
				$seoDescription = htmlspecialchars(stripcslashes(get_post_meta($post_id, '_aioseop_description', true)));
			} else {
				$seoDescription = apply_filters('hn_fbog_get_excerpt', get_excerpt_by_id($post->ID) );
			}
		}
		if ( isset ($options['hn_fbog_title'] ) && !empty ($options['hn_fbog_title']) ) {
			echo '<meta property="og:title" content="' . esc_attr( apply_filters( 'hn_fbog_title', $options['hn_fbog_title'] ) ) . '"/>' . "\n";
		} else if (isset ($options['hn_fbog_seotitle'] ) && $options['hn_fbog_seotitle'] == 'yes' && !empty ($seoTitle) ) {
			echo '<meta property="og:title" content="' . esc_attr( apply_filters( 'hn_fbog_title', $seoTitle ) ) . '"/>' . "\n";
		} else {
			echo '<meta property="og:title" content="' . esc_attr( apply_filters( 'hn_fbog_title', $hn_fbog_title ) ) . '"/>' . "\n";
		}
		echo '<meta property="og:site_name" content="' . get_bloginfo( 'name' ) . '"/>' . "\n";
		if (isset ($options['hn_fbog_description'] ) && !empty ($options['hn_fbog_description']) ) {
			echo '<meta property="og:description" content="' . esc_attr( apply_filters( 'hn_fbog_description', $options['hn_fbog_description'] ) ) . '"/>' . "\n";
		} else if (isset ($options['hn_fbog_seodescription'] ) &&  $options['hn_fbog_seodescription'] == 'yes' && !empty ($seoDescription) ) {
			echo '<meta property="og:description" content="' . esc_attr( apply_filters( 'hn_fbog_description', $seoDescription ) ) . '"/>' . "\n";
		} else {
			echo '<meta property="og:description" content="' . esc_attr( apply_filters( 'hn_fbog_description', $hn_fbog_description ) ) . '"/>' . "\n";
		}
		if ( is_single() ) {
			$hn_fbog_type = 'article';
		} else {
			$hn_fbog_type = 'website';
		}
		echo '<meta property="og:type" content="' . esc_attr( apply_filters( 'hn_fbog_type', $hn_fbog_type ) ) . '"/>' . "\n";
		$hn_fbog_images = array();
		if ( !is_home() && $options['hn_fbog_force_fallback'] != 1 ) {
			if ( function_exists( 'has_post_thumbnail' ) && has_post_thumbnail( $post->ID ) ) {
				$thumbnail_src = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'medium' );
				$hn_fbog_images[] = $thumbnail_src[0]; 
			}
			if ( hn_fbog_find_images() !== false && is_singular() ) { 
				$hn_fbog_images = array_merge( $hn_fbog_images, hn_fbog_find_images() ); 
			}
		}
		if ( isset( $options['hn_fbog_fallback_img'] ) && $options['hn_fbog_fallback_img'] != '') {
			$hn_fbog_images[] = $options['hn_fbog_fallback_img']; 
		}
		if ( !empty( $hn_fbog_images ) && is_array( $hn_fbog_images ) ) {
			foreach ( $hn_fbog_images as $image ) {
				echo '<meta property="og:image" content="' . esc_url( apply_filters( 'hn_fbog_image', $image ) ) . '"/>' . "\n";
			}
		} else {
			echo "<!-- Il n'y a pas d'image définie par défaut -->\n"; 
		}
		echo '<meta property="og:locale" content="' . strtolower( esc_attr( get_locale() ) ) . '"/>' . "\n";
		echo "<!-- /Hobbynote Facebook Open Graph -->\n";
	}
}

// Ajout des actions de création des balises meta, d'initialisation du plugin et d'ajout des menus et des options du plugin
add_action('wp_head','hn_fbog_build_head',50);
add_action('admin_init','hn_fbog_init');
add_action('admin_menu','hn_fbog_add_options');

// Fonction d'initialisation du plugin
function hn_fbog_init() {
	register_setting('hn_fbog_options','hn_fbog','hn_fbog_validate');
}

// Fonction d'ajout des menus et des options du plugin
function hn_fbog_add_options() {
	$plugin_menu = add_menu_page('Hobbynote Facebook Open Graph Options', 'Facebook OG', 'manage_options', 'hn_fbog_configuration', 'hn_fbog_configuration_page', plugins_url('admin/img/facebook.png', __FILE__));
	$configuration_page = add_submenu_page( 'hn_fbog_configuration', 'Configuration', 'Configuration', 'manage_options', 'hn_fbog_configuration', 'hn_fbog_configuration' );
	$seo_page = add_submenu_page( 'hn_fbog_configuration', 'SEO', 'SEO', 'manage_options', 'hn_fbog_seo', 'hn_fbog_seo_page' );
	$documentation_link = add_submenu_page( 'hn_fbog_configuration', 'Documentation', 'Documentation' , 'manage_options', 'javascript:void((function(){window.open("https://developers.facebook.com/docs/opengraph")})())', '' );
	$debuger_link = add_submenu_page( 'hn_fbog_configuration', 'Debuger', 'Debuger' , 'manage_options', 'javascript:void((function(){window.open("https://www.hobbynote.com/smo-analyzer/")})())', '' );
	add_action('load-' . $configuration_page, 'hn_fbog_load_admin_scripts');
	add_action('load-' . $seo_page, 'hn_fbog_load_admin_scripts');
}

// Fonction de chargement des scripts admin
function hn_fbog_load_admin_scripts() {
	add_action('admin_enqueue_scripts', 'hn_fbog_admin_script');
}

// Fontion d'ajout de la feuille de style 
function hn_fbog_admin_script() {
	wp_enqueue_style('hn-fbog-admin-style', plugins_url('admin/css/hn-fbog-admin.css', __FILE__));
}
	
// Fonction de création de la page "Configuration"
function hn_fbog_configuration_page() {
	$hn_fbog_logo = plugins_url('admin/img/hn-facebook-open-graph.jpg', __FILE__);
?>
	<div class="hn-fbog" id="pluginwrapper">
		<div class="blocks head-block">
			<aside class="header">
				<div class="box">
					<img id="hn_fbog_logo" src="<?php echo $hn_fbog_logo ?>" />
					<p class="plugin-desc"><?php _e('Les balises Open Graph permettent d\'enrichir et de mettre en valeur votre contenu lorsqu\'il est partagé sur les réseaux sociaux.  <br />Cela permet d\'augmenter le traffic vers votre site ainsi que le nombre de partage de votre contenu et donc améliorer votre référencement.', 'hn-fbog'); ?></p>
				</div>    
				<div class="notification hide"></div>
			</aside>
		</div>
		<div class="blocks body-block">
			<form method="post" action="options.php" id="hn-fbog-form"><?php settings_fields('hn_fbog_options'); ?>
				<?php $options = get_option('hn_fbog'); ?>
				<section class="postbox" id="tab">
					<h1 class="hndle">Configuration</h1>
					<p class="about_desc">
						<label class="labeltext" for="hn_fbog[hn_fbog_app_id]">Facebook Application ID :</label>
						<input type="text" name="hn_fbog[hn_fbog_admin_ids]" value="<?php echo $options['hn_fbog_app_id']; ?>" class="regular-text" /><br /><?php _e('Valeur numérique obtenue lors de la création de votre application Facebook.<br />Si vous n\'avez pas encore créé d\'application, cliquez ici : <a href="https://www.facebook.com/developers/apps.php" target="_blank">Créer une application</a>') ?>
					</p>
					<p class="about_desc">
						<label class="labeltext" for="facebookAdminsId">Facebook Admins ID :</label>
						<input type="text" name="hn_fbog[hn_fbog_admin_ids]" value="<?php echo $options['hn_fbog_admin_ids']; ?>" class="regular-text" /><br /><?php _e('ID Facebook numériques. Séparer les ID avec des virgules si vous souhaitez que plusieurs utilisateurs puissent accéder aux Insights. <br />Pour obtenir un id, rendez vous sur <a href="http://graph.facebook.com/[username]" target="_blank">http://graph.facebook.com/[username]</a>, <strong>[username]</strong> étant le nom de l\'utilisateur.') ?>
					</p>
					<p class="about_desc">
						<label class="labeltext" for="hn_fbog[hn_fbog_fallback_img]">URL de l'image par défaut :</label>
						<input type="text" name="hn_fbog[hn_fbog_fallback_img]" value="<?php echo $options['hn_fbog_fallback_img']; ?>" class="regular-text" /><br /><?php _e('URL de l\'image à utiliser par défaut (si il n\'y a pas d\'image dans le contenu partagé). <br /><strong>La taille recommandée pour l\'image est de 200px par 200px</strong>.<br />') ?>
					</p>
					<p>
						<input type="checkbox" name="hn_fbog[hn_fbog_force_fallback]" value="1" <?php if ($options['hn_fbog_force_fallback'] == 1) echo 'checked="checked"'; ?>) /><?php _e('Forcer l\'image par défaut') ?>
						<p class="about_desc" style="position:relative; bottom:10px">Activer cette option pour remplacer l'image des contenus partagés automatiquement par l'image par défaut.</p>
					</p>
					<div class="form-status"></div>
					<div class="form-loading hide" style="background-image:url(' <?php plugins_url('admin/img/loading.gif', __FILE__) ?> ')">
						<span class="text-loading">SAVING...</span>
					</div>
					<input type="submit" name="hn_fbog_submit" class="submit" value="<?php _e('Enregistrer') ?>" />
					<div id="disable-but-necessary" style="display:none">
						<p>
							<label class="labeltext" for="seoTitle"><?php _e('Utiliser les données entrées avec le plugin WPSEO by Yoast ou ALL In One SEO pour le champ titre (<strong>oui par défaut</strong>)', 'hn-fbog'); ?> :</label>
							<select class="styled-select"  id="hn_fbog_seotitle" name="hn_fbog[hn_fbog_seotitle]">
								<option value="yes" <?php echo $options['hn_fbog_seotitle'] == 'yes' ? 'selected="selected"' : ''; ?>>Oui</option>
								<option value="no" <?php echo $options['hn_fbog_seotitle'] == 'no' ? 'selected="selected"' : ''; ?>>Non</option>
							</select>
						</p> 
						<p>
							<label class="labeltext" for="seoDescriptionn"><?php _e('Utiliser les données entrées avec le plugin WPSEO by Yoast ou ALL In One SEO pour le champ description (<strong>oui par défaut</strong>)', 'hn-fbog'); ?> :</label>
							<select class="styled-select"  id="hn_fbog_seodescription" name="hn_fbog[hn_fbog_seodescription]">
								<option value="yes" <?php echo $options['hn_fbog_seodescription'] == 'yes' ? 'selected="selected"' : ''; ?>>Oui</option>
								<option value="no" <?php echo $options['hn_fbog_seodescription'] == 'no' ? 'selected="selected"' : ''; ?>>Non</option>
							</select>
						</p> 
						<h2>Si vous souhaitez utiliser vos propres champs</h2>
						<p>
							<label class="labeltext" for="title"><?php _e('Entrez un titre pour l\'Open Graph', 'hn-fbog'); ?> :</label>
							<input id="hn_fbog_title" type="text" name="hn_fbog[hn_fbog_title]" class="regular-text" value="<?php echo $options['hn_fbog_title']; ?>" />
						</p>
						<p>
							<label class="labeltext" for="description"><?php _e('Entrez une description pour l\'Open Graph', 'hn-fbog'); ?> :</label>
							<input id="hn_fbog_description" type="text" name="hn_fbog[hn_fbog_description]" class="regular-text" value="<?php echo $options['hn_fbog_description']; ?>" />
						</p>
					</div>
				</section>
			</form>
		</div>
	</div>
<?php
}

// Fonction de création de la page "SEO"
function hn_fbog_seo_page() {
	$hn_fbog_logo = plugins_url('admin/img/hn-facebook-open-graph.jpg', __FILE__);
?>
	<div class="hn-fbog" id="pluginwrapper">
		<div class="blocks head-block">
			<aside class="header">
				<div class="box">
					<img id="hn_fbog_logo" src="<?php echo $hn_fbog_logo ?>" />
					<p class="plugin-desc"><?php _e('Les balises Open Graph permettent d\'enrichir et de mettre en valeur votre contenu lorsqu\'il est partagé sur les réseaux sociaux.  <br />Cela permet d\'augmenter le traffic vers votre site ainsi que le nombre de partage de votre contenu et donc améliorer votre référencement.', 'hn-fbog'); ?></p>
				</div>    
				<div class="notification hide"></div>
			</aside>
		</div>
		<div class="blocks body-block">
			<form method="post" action="options.php" id="hn-fbog-form"><?php settings_fields('hn_fbog_options'); ?>
				<?php $options = get_option('hn_fbog'); ?>
				<section class="postbox" id="tab">
					<div id="disable-but-necessary" style="display:none">
						<p class="about_desc">
							<label class="labeltext" for="facebookAppId">Facebook Application ID :</label>
							<input type="text" name="hn_fbog[hn_fbog_app_id]" value="<?php echo $options['hn_fbog_app_id']; ?>" class="regular-text" /><br /><?php _e('Valeur numérique obtenue lors de la création de votre application Facebook.<br />Si vous n\'avez pas encore créé d\'application, cliquez ici : <a href="https://www.facebook.com/developers/apps.php" target="_blank">Créer une application</a>') ?>
						</p>
						<p class="about_desc">
							<label class="labeltext" for="facebookAdminsId">Facebook Admins ID :</label>
							<input type="text" name="hn_fbog[hn_fbog_admin_ids]" value="<?php echo $options['hn_fbog_admin_ids']; ?>" class="regular-text" /><br /><?php _e('ID Facebook numériques. Séparer les ID avec des virgules si vous souhaitez que plusieurs utilisateurs puissent accéder aux Insights. <br />Pour obtenir un id, rendez vous sur <a href="http://graph.facebook.com/[username]" target="_blank">http://graph.facebook.com/[username]</a>, <strong>[username]</strong> étant le nom de l\'utilisateur.') ?>
						</p>
						<p class="about_desc">
							<label class="labeltext" for="facebookImgUrl">URL de l'image par défaut :</label>
							<input type="text" name="hn_fbog[hn_fbog_fallback_img]" value="<?php echo $options['hn_fbog_fallback_img']; ?>" class="regular-text" /><br /><?php _e('URL de l\'image à utiliser par défaut (si il n\'y a pas d\'image dans le contenu partagé). <br /><strong>La taille recommandée pour l\'image est de 200px par 200px</strong>.<br />') ?>
						</p>
						<p>
							<input type="checkbox" name="hn_fbog[hn_fbog_force_fallback]" value="1" <?php if ($options['hn_fbog_force_fallback'] == 1) echo 'checked="checked"'; ?>) /><?php _e('Forcer l\'image par défaut') ?>
							<p class="about_desc" style="position:relative; bottom:10px">Activer cette option pour remplacer l'image des contenus partagés automatiquement par l'image par défaut.</p>
						</p>
					</div>
					<h1 class="hndle">SEO</h1>
					<h2>Récupérer les données depuis des plugins SEO</h2>                                
					<p>
						<label class="labeltext" for="seoTitle"><?php _e('Utiliser les données entrées avec le plugin WPSEO by Yoast ou ALL In One SEO pour le champ titre (<strong>oui par défaut</strong>)', 'hn-fbog'); ?> :</label>
						<select class="styled-select"  id="hn_fbog_seotitle" name="hn_fbog[hn_fbog_seotitle]">
							<option value="yes" <?php echo $options['hn_fbog_seotitle'] == 'yes' ? 'selected="selected"' : ''; ?>>Oui</option>
							<option value="no" <?php echo $options['hn_fbog_seotitle'] == 'no' ? 'selected="selected"' : ''; ?>>Non</option>
						</select>
					</p> 
					<p>
						<label class="labeltext" for="seoDescriptionn"><?php _e('Utiliser les données entrées avec le plugin WPSEO by Yoast ou ALL In One SEO pour le champ description (<strong>oui par défaut</strong>)', 'hn-fbog'); ?> :</label>
						<select class="styled-select"  id="hn_fbog_seodescription" name="hn_fbog[hn_fbog_seodescription]">
							<option value="yes" <?php echo $options['hn_fbog_seodescription'] == 'yes' ? 'selected="selected"' : ''; ?>>Oui</option>
							<option value="no" <?php echo $options['hn_fbog_seodescription'] == 'no' ? 'selected="selected"' : ''; ?>>Non</option>
						</select>
					</p> 
					<h2>Si vous souhaitez utiliser vos propres champs</h2>
					<p>
						<label class="labeltext" for="title"><?php _e('Entrez un titre pour l\'Open Graph', 'hn-fbog'); ?> :</label>
						<input id="hn_fbog_title" type="text" name="hn_fbog[hn_fbog_title]" class="regular-text" value="<?php echo $options['hn_fbog_title']; ?>" />
					</p>
					<p>
						<label class="labeltext" for="description"><?php _e('Entrez une description pour l\'Open Graph', 'hn-fbog'); ?> :</label>
						<input id="hn_fbog_description" type="text" name="hn_fbog[hn_fbog_description]" class="regular-text" value="<?php echo $options['hn_fbog_description']; ?>" />
					</p>
					<div class="form-status"></div>
					<div class="form-loading hide" style="background-image:url(' <?php plugins_url('admin/img/loading.gif', __FILE__) ?> ')">
						<span class="text-loading">SAVING...</span>
					</div>
					<input type="submit" name="hn_fbog_submit" class="submit" value="<?php _e('Enregistrer') ?>" />
				</section>
			</form>
		</div>
	</div>
<?php
}

// Fonction de validation des options
function hn_fbog_validate($input) {
	$input['hn_fbog_admin_ids'] = wp_filter_nohtml_kses($input['hn_fbog_admin_ids']);
	$input['hn_fbog_app_id'] = wp_filter_nohtml_kses($input['hn_fbog_app_id']);
	$input['hn_fbog_fallback_img'] = wp_filter_nohtml_kses($input['hn_fbog_fallback_img']);
	$input['hn_fbog_force_fallback'] = ($input['hn_fbog_force_fallback'] == 1)  ? 1 : 0;
	$input['hn_fbog_title'] = wp_filter_nohtml_kses($input['hn_fbog_title']);
	$input['hn_fbog_description'] = wp_filter_nohtml_kses($input['hn_fbog_description']);
	$input['hn_fbog_seotitle'] = wp_filter_nohtml_kses($input['hn_fbog_seotitle']);
	$input['hn_fbog_seodescription'] = wp_filter_nohtml_kses($input['hn_fbog_seodescription']);
	return $input;
}

// Ajout de l'action check doublon
add_action('after_setup_theme','hn_fbog_fix_excerpts_exist');

// Fonction check doublon
function hn_fbog_fix_excerpts_exist() {
	remove_filter('get_the_excerpt','twentyten_custom_excerpt_more');
	remove_filter('get_the_excerpt','twentyeleven_custom_excerpt_more');
}

// Fonction d'ajout des liens de configuration du plugin
function hn_fbog_add_settings_link($links, $file) {
	static $this_plugin;
	if (!$this_plugin) $this_plugin = plugin_basename(__FILE__);
	if ($file == $this_plugin){
		$settings_link = '<a href="options-general.php?page=hn_fbog_configuration">'.__("Settings","configuration").'</a>';
		array_unshift($links, $settings_link);
	}
	return $links;
}

add_filter('plugin_action_links','hn_fbog_add_settings_link', 10, 2 );

// Fonction de désinstallation du plugin
if (function_exists('register_uninstall_hook')) {
    register_uninstall_hook(__FILE__, 'hn_fbog_uninstall_hook');
	function hn_fbog_uninstall_hook() {
		delete_option('hn_fbog');
	}
}

?>