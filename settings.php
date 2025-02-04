<?php
//!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
//https://wpengineer.com/2426/wp_list_table-a-step-by-step-guide/
//!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!

//importo WP_List_Table se non esiste
if (!class_exists('WP_List_Table')) {
	require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

//creo classe personalizzata
class Photos_List_Table extends WP_List_Table
{

	//costruttore
	function __construct()
	{
		parent::__construct(array('ajax' => true));
	}

	//ritorno nomi delle colonne
	function get_columns()
	{
		return array(
			'cb'        => '<input type="checkbox" />',
			'id' 		=> 'ID',
			'path' 		=> 'Anteprima',
			'title'		=> 'Titolo',
			'description'  => 'Descrizione',
			'visible'	=> 'Visibilità'
		);
	}

	function get_bulk_actions()
	{
		$actions = array(
			'delete'    => 'Cancella',
			'show'    => 'Mostra',
			'hide'    => 'Nascondi'
		);
		return $actions;
	}

	function column_cb($item)
	{
		return sprintf(
			'<input type="checkbox" name="ids[]" value="%s" />',
			$item->id
		);
	}

	function column_default($item, $column_name)
	{
		switch ($column_name) {
			case 'path':
				$actions = array(
					$item->visible ? 'hide' : 'show'	=> sprintf('<a href="%s">%s</a>', add_query_arg(array('action' => $item->visible ? 'hide' : 'show', 'id' => $item->id)), $item->visible ? 'Nascondi' : 'Mostra'),
					'delete'	=> sprintf('<a href="%s">Elimina</a>', add_query_arg(array('action' => 'delete', 'id' => $item->id))),
				);

				return sprintf('<img height="30" width="50" src="%1$s" alt="%1$s"/> %2$s', $item->$column_name, $this->row_actions($actions));
				break;
			case 'visible':
				return '<span class="dashicons ' . ($item->visible ? 'dashicons-visibility' : 'dashicons-hidden') . '"></span>';
				break;
			default:
				return $item->$column_name;
		}
	}

	protected function get_views()
	{
		$views = array('Tutti', 'Nascosti', 'Visibili');
		$current = (!empty($_REQUEST['photo_tag']) ? $_REQUEST['photo_tag'] : 'Tutti');

		//ciclo per creare links
		foreach ($views as &$view) {
			if ($view == 'Tutti') {
				$arg = remove_query_arg('photo_tag');
			} else {
				$arg = add_query_arg('photo_tag', $view);
			}

			$view = '<a href="' . $arg . '"' . ($current == $view ? ' class="current"' : '') . '>' . $view . '</a>';
		}

		return $views;
	}

	function usort_reorder($a, $b)
	{
		// If no sort, default to title
		$orderby = (!empty($_GET['orderby'])) ? $_GET['orderby'] : 'id';
		// If no order, default to asc
		$order = (!empty($_GET['order'])) ? $_GET['order'] : 'desc';
		// Determine sort order
		$result = strcmp($a->$orderby, $b->$orderby);
		// Send final sort direction to usort
		return ($order === 'asc') ? $result : -$result;
	}

	function get_sortable_columns()
	{
		$sortable_columns = array(
			'id' 		 => array('id', false),
			'title'		 => array('title', false)
		);
		return $sortable_columns;
	}

	//ritorno items
	function prepare_items()
	{
		global $wpdb;

		$per_page = 10;
		$current_page = $this->get_pagenum();
		if (1 < $current_page) {
			$offset = $per_page * ($current_page - 1);
		} else {
			$offset = 0;
		}

		$items = get_photos_from_bte_theme_showcase(true);

		//rimuovo elementi non selezionati
		foreach ($items as $item) {
			if (isset($_REQUEST['photo_tag']))
				if (($_REQUEST['photo_tag'] == 'Nascosti' && $item->visible == true) || ($_REQUEST['photo_tag'] == 'Visibili' && $item->visible == false)) {
					continue;
				}

			$this->items[] = $item;
		}

		$columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array($columns, $hidden, $sortable);

		usort($items, array(&$this, 'usort_reorder'));
		$count = $wpdb->get_var("SELECT COUNT(id) FROM  {$wpdb->prefix}bte_theme_showcase;");

		// Set the pagination
		$this->set_pagination_args(array(
			'total_items' => $count,
			'per_page'    => $per_page,
			'total_pages' => ceil($count / $per_page)
		));
	}
}

function get_photo_list()
{
	$media_query = new WP_Query(
		array(
			'post_type' => 'attachment',
			'post_status' => 'inherit',
			'posts_per_page' => -1,
		)
	);
	$list = array();
	foreach ($media_query->posts as $post) {
		$list[] = $post; #wp_get_attachment_url($post->ID);
	}
	return $list;
}

function render_bte_theme_menu_page()
{
	//creo tabella
	$table = new Photos_List_Table();

	//controllo azioni
	if (!empty($_REQUEST['action']) && !empty($_REQUEST['id'])) {
		switch ($_REQUEST['action']) {
			case 'delete':
				//cancello la foto
				delete_photo_from_bte_theme_showcase($_REQUEST['id']);
				break;
			case 'hide':
				//cambio la visibilità della foto a false
				hide_photo_of_bte_theme_showcase($_REQUEST['id']);
				break;
			case 'show':
				//cambio la visibilità della foto a true
				show_photo_of_bte_theme_showcase($_REQUEST['id']);
				break;
		}
	} else if (!empty($table->current_action()) && !empty($_REQUEST['ids'])) {
		switch ($table->current_action()) {
			case 'delete':
				//cancello ogni foto selezionata
				foreach ($_REQUEST['ids'] as $id) {
					delete_photo_from_bte_theme_showcase($id);
				}
				break;
			case 'hide':
				//cambio la visibilità delle foto selezionate
				foreach ($_REQUEST['ids'] as $id) {
					hide_photo_of_bte_theme_showcase($id);
				}
				break;
			case 'show':
				//cambio la visibilità delle foto selezionate
				foreach ($_REQUEST['ids'] as $id) {
					show_photo_of_bte_theme_showcase($id);
				}
				break;
		}
	}

?>
	<div class="wrap pmc-fs">
		<h1 class="wp-heading-inline">Impostazioni del tema</h1>
		<button class="page-title-action bte-upload">Aggiungi foto</button>
		<hr class="wp-header-end">

		<?php if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'upload') { ?>
			<form method="post">
				<?= json_encode(get_photo_list()); ?>

			<?php } else {
			$table->prepare_items(); ?>
				<input type="hidden" name="page" value="" />
				<?php $table->views(); ?>
				<form method="post">
				<?php
				// Stampo la tabella per le immagini
				$table->display();
			}
				?>
				</form>
				<form method="post" action="options.php">
					<?php
					settings_fields("bte_theme_menu_page");
					// Stampo le opzioni
					do_settings_sections("bte_theme_menu_page");

					// Ed ora il pulstane per salvare
					submit_button();
					?>
				</form>
	</div>
<?php
}

function add_bte_theme_menu_item()
{
	// Aggiungo una pagina
	add_submenu_page('themes.php', 'Impostazioni della galleria', 'Galleria', 'edit_theme_options', 'bte_theme_menu_page', 'render_bte_theme_menu_page');

	// La sezione con i campi
	add_settings_section('bte_sentences_section', 'Frasi della Homescreen', null, 'bte_theme_menu_page');
	add_settings_section('bte_discord_section', 'Collegamenti con Discord', null, 'bte_theme_menu_page');

	//Salvo il dato
	register_setting('bte_theme_menu_page', 'bte_main_sentence');
	register_setting('bte_theme_menu_page', 'bte_second_sentences', ['type' => 'array', 'description' => 'Inserisci una frase per riga']);
	register_setting('bte_theme_menu_page', 'bte_discord_url');
	register_setting('bte_theme_menu_page', 'bte_discord_add_utm', ['type' => 'boolean']);
	register_setting('bte_theme_menu_page', 'bte_discord_widget_url');

	add_settings_field('bte_main_sentence', 'Frase primaria', 'bte_fill_main_sentence', 'bte_theme_menu_page', 'bte_sentences_section');
	add_settings_field('bte_second_sentences', 'Frasi secondarie', 'bte_fill_second_sentences', 'bte_theme_menu_page', 'bte_sentences_section');
	add_settings_field('bte_discord_url', 'URL al server Discord', 'bte_fill_discord_url', 'bte_theme_menu_page', 'bte_discord_section');
	add_settings_field('bte_discord_add_utm', 'Usare i tag UTM?', 'bte_fill_discord_add_utm', 'bte_theme_menu_page', 'bte_discord_section');
	add_settings_field('bte_discord_widget_url', 'URL del widget Discord', 'bte_fill_discord_widget_url', 'bte_theme_menu_page', 'bte_discord_section');
}
add_action('admin_menu', 'add_bte_theme_menu_item');

//aggiungo supporto a script per usare galleria media
function bte_load_media_scripts($page)
{
	if ($page != 'appearance_page_bte_theme_menu_page')
		return;

	wp_enqueue_media();
	wp_enqueue_script('bte_script_admin_media');
}
add_action('admin_enqueue_scripts', 'bte_load_media_scripts');

//risposta ajax
add_action('wp_ajax_bte_add_image_via_ajax', 'bte_add_image_via_ajax');
function bte_add_image_via_ajax()
{
	if (!isset($_GET['images'])) {
		wp_send_json_error(
			new WP_Error('i1', 'Non sono state fornite immagini.'),
			500
		);
		return;
	}

	foreach ($_GET['images'] as $id) {
		//ottengo dati relativi ad immagine
		$src = wp_get_attachment_image_url($id, [660, 388]);
		$title = get_the_title($id);
		$description = get_post($id)->post_content;

		//aggiungo foto al database
		add_photo_to_bte_theme_showcase(
			$id,
			$src,
			$title,
			$description
		);
	}

	//Successo!
	wp_send_json_success(null, 201);
}


function bte_fill_main_sentence()
{
?>
	<input type="text" name="bte_main_sentence" id="bte_main_sentence" value="<?= get_option('bte_main_sentence'); ?>" />
<?php
}

function bte_fill_second_sentences()
{
?>
	<textarea name="bte_second_sentences" id="bte_second_sentences"><?= get_option('bte_second_sentences'); ?></textarea>
<?php
}

function bte_fill_discord_url()
{
?>
	<input type="text" name="bte_discord_url" id="bte_discord_url" required value="<?= get_option('bte_discord_url', 'https://discord.com/invite/dMahHCH'); ?>" />
<?php
}

function bte_fill_discord_add_utm()
{
?>
	<input type="checkbox" name="bte_discord_add_utm" id="bte_discord_add_utm" value="1" <?php checked(1, get_option('bte_discord_add_utm'), true); ?>" />
<?php
}

function bte_fill_discord_widget_url()
{
?>
	<input type="text" name="bte_discord_widget_url" id="bte_discord_widget_url" required value="<?= get_option('bte_discord_widget_url', 'https://discordapp.com/widget?id=686910132017430538&amp;theme=light'); ?>" />
<?php
}
?>