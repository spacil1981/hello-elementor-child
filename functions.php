<?php

////////////////////////////////////////////////////////
//do not change following function
////////////////////////////////////////////////////////

/**
 * Function to enqueue stylesheet from parent theme
 */
function child_enqueue__parent_scripts() {
	wp_enqueue_style( 'parent', get_template_directory_uri() . '/style.css' );
}
add_action( 'wp_enqueue_scripts', 'child_enqueue__parent_scripts');

////////////////////////////////////////////////////////
// place for custom code
////////////////////////////////////////////////////////

/**
* pridanie dodatočných info vo woocommerce košíku 
*/
function wc_cart_display_product_info($product_name, $cart_item) {
    if (is_cart()) {
        $product_id = $cart_item['product_id'];

        $product = wc_get_product($product_id);
        $product_name = $product->get_name();

        $product_attributes = get_post_meta($product_id, '_product_attributes', true);
        $selected_attributes = array('typ-vstupenky', 'datum-podujatia');
        $additional_info = '';

        foreach ($selected_attributes as $attribute_name) {
            if (isset($product_attributes[$attribute_name]) && !empty($product_attributes[$attribute_name]['value'])) {
                $additional_info .= '<span class="' . esc_attr($attribute_name) . '">' . esc_html($product_attributes[$attribute_name]['value']) . '</span>';
            }
        }

        return $product_name . $additional_info;
    }

    return $product_name;
}
add_filter('woocommerce_cart_item_name', 'wc_cart_display_product_info', 10, 2);

/**
 * pridanie dodatočných info vo woocommerce checkoute 
 */
function wc_checkout_display_ticket_type($product_name, $cart_item) {
    if (is_checkout()) {
        $product_id = $cart_item['product_id'];

        $ticket_type = get_post_meta($product_id, 'typ-vstupenky', true);

        if (!empty($ticket_type)) {
            $product_name .= '<span class="typ-vstupenky">' . esc_html($ticket_type) . '</span>';
        }
    }

    return $product_name;
}
add_filter('woocommerce_cart_item_name', 'wc_checkout_display_ticket_type', 10, 2);

/**
 * zobrazenie vsetkych atributov produktu cez skrateny kod 
 */
function display_product_properties($atts) {
    $atts = shortcode_atts(
        array(
            'product_id' => 0,
        ),
        $atts,
        'product_properties'
    );

    $product_id = intval($atts['product_id']);
    $product = wc_get_product($product_id);

    if ($product) {
        $all_product_properties = get_post_meta($product_id);

        $output = '<div class="product-properties">';
        
        if (!empty($all_product_properties)) {
            foreach ($all_product_properties as $property_name => $property_value) {
                $output .= '<p><strong>' . esc_html($property_name) . ':</strong> ' . esc_html($property_value[0]) . '</p>';
            }
        }

        $output .= '<p><strong>' . esc_html__('SKU:', 'hello-elementor-child') . ':</strong> ' . esc_html($product->get_sku()) . '</p>';       
        $output .= '<p><strong>' . esc_html__('Ticket type:', 'hello-elementor-child') . ':</strong> ' . esc_html($product->get_attribute('pa_typ-vstupenky')) . '</p>';
        $output .= '<p><strong>' . esc_html__('Venue:', 'hello-elementor-child') . ':</strong> ' . esc_html($product->get_attribute('pa_miesto-konania')) . '</p>';
        $output .= '<p><strong>' . esc_html__('Year:', 'hello-elementor-child') . ':</strong> ' . esc_html($product->get_attribute('pa_rok')) . '</p>';

        $output .= '</div>';

        return $output;
    } else {
		return '<p><strong>' . sprintf(esc_html__('Produkt with ID %d was not found.', 'hello-elementor-child'), esc_html($product_id)) . '</strong></p>';
    }
}
add_shortcode('product_properties', 'display_product_properties');

/**
 * funkcia pre detekciu preview módu v elementore
 */
function is_elementor_preview_mode() {
    return \Elementor\Plugin::$instance->preview->is_preview_mode();
}

/**
 * funkcia pre detekciu edit módu v elementore
 */
function is_elementor_edit_mode() {
    return \Elementor\Plugin::$instance->editor->is_edit_mode();
}

/**
 * short code pre generovanie vstupenkovych formularov 
 */
function vstupenky_formulare($atts = [], $content = null, $tag = '') {
	ob_start();

	// normalize attribute keys, lowercase	
	$atts = array_change_key_case( (array) $atts, CASE_LOWER );

	// override default attributes with user attributes
	$user_atts = shortcode_atts( 
		array (),
		$atts,
		$tag
	);
    
    if (is_checkout() || is_elementor_preview_mode() || is_elementor_edit_mode()) {
        try {
            $cart_contents = WC()->cart->get_cart();
            vygenerujFormulare($cart_contents);
        } catch(Exception $e) {
            echo __('ERROR: ', 'hello-elementor-child') . $e->getMessage();
        }
    }

	return ob_get_clean();
}
add_action('woocommerce_checkout_init', 'vstupenky_formulare');
add_action('woocommerce_add_to_cart', 'vstupenky_formulare');
add_action('woocommerce_before_calculate_totals', 'vstupenky_formulare');
add_action('woocommerce_cart_updated', 'vstupenky_formulare');
add_shortcode('vstupenky', 'vstupenky_formulare');

/**
 * shortcode pre vstupenkovu nastenku
 */
function vstupenky_dashboard($atts = [], $content = null, $tag = '') {
    if (!is_user_logged_in()) {
		return esc_html__('You must be logged in to view your orders.', 'hello-elementor-child');
    }

    // normalize attribute keys, lowercase    
    $atts = array_change_key_case((array) $atts, CASE_LOWER);

    // override default attributes with user attributes
    $user_atts = shortcode_atts(
        array(
			'mobile' => false
		),
        $atts,
        $tag
    );

	$mobile_view = filter_var($user_atts['mobile'], FILTER_VALIDATE_BOOLEAN);

    $user_id = get_current_user_id();
    $customer_orders = wc_get_orders(array(
        'customer' => $user_id,
    ));

    ob_start();

    if (empty($customer_orders)) {
		echo '<div class="error">' . esc_html__('You have no orders.', 'hello-elementor-child') . '</div>';
    } else {
        try {
            $orders_data = [];
            foreach ($customer_orders as $order) {
                $orders_data[] = [
                    'id' => $order->get_id(),
                    'date' => $order->get_date_created()->date("Y-m-d H:i:s"),
                    'status' => $order->get_status(),
                    'meta_data' => $order->get_meta_data(),
                ];
            }

            include(__DIR__ . '/short-templates/vstupenky_dashboard' . ($mobile_view ? '_mobile' : '') . '.php');

        } catch (Exception $e) {
            echo __('ERROR: ', 'hello-elementor-child') . $e->getMessage();
        }
    }

    return ob_get_clean();
}
add_shortcode('vstupenky_dashboard', 'vstupenky_dashboard');

/**
 * shortcode pre vstupenkovy formular
 */
function vstupenky_form($atts) {
	ob_start();

    if (!is_user_logged_in()) {
		echo '<div class="error">' . esc_html__('You must be logged in to view your orders.', 'hello-elementor-child') . '</div>';
        return ob_get_clean();
    }

	$my_current_lang = apply_filters( 'wpml_current_language', NULL );

	$order_id_attribute = '';
	if($my_current_lang == 'sk') {
		$order_id_attribute = 'order-id';
	} else {
		$order_id_attribute = 'order-id';
	}

    $atts = shortcode_atts(array(
        $order_id_attribute => 0,
		'edit' => false
    ), $atts, 'vstupenky_form');

	$edit_mode = filter_var($atts['edit'], FILTER_VALIDATE_BOOLEAN);
	$order_id = intval($atts[$order_id_attribute]);
	
	if ($order_id <= 0 && isset($_GET[$order_id_attribute]) && is_numeric($_GET[$order_id_attribute])) {
		$order_id = intval($_GET[$order_id_attribute]);
	}	

    if ($order_id <= 0) {
		echo '<div class="error">' . esc_html__('Invalid or missing order ID.', 'hello-elementor-child') . '</div>';
		return ob_get_clean();
    }

    $order = wc_get_order($order_id);
    
    if (!$order || $order->get_user_id() !== get_current_user_id()) {
		echo '<div class="error">' . esc_html__('Order does not exist or you do not have permission to view it.', 'hello-elementor-child') . '</div>';
		return ob_get_clean();
    }

	$order_items = [];
	$meta_data = $order->get_meta_data();
	$counter = 0;

	foreach ($order->get_items() as $item_id => $item) {
		$ticket_data = [];

		for ($i = 1; $i <= $item['quantity']; $i++) {
			$counter++;
			$ticket_number = count($ticket_data) + 1;

			if (!isset($ticket_data[$ticket_number])) {
				$ticket_data[$ticket_number] = [];
			}

			foreach ($meta_data as $meta) {
				if (preg_match('/vstupenka-'. $counter .'-/', $meta->key, $matches)) {					
					$field_name = str_replace("vstupenka-{$counter}-", '', $meta->key);
					$ticket_data[$ticket_number][$field_name] = $meta->value;
				}
			}
		}

		$order_items[$item_id] = [
			'product_id' => $item->get_product_id(),
			'quantity' => $item->get_quantity(),
			'data' => $item->get_data(),
			'order_id' => $order_id,
			'tickets' => $ticket_data,
			'edit_mode' => $edit_mode
		];		
    }

	try {
		vygenerujFormulare($order_items);
	} catch (Exception $e) {
		echo __('ERROR: ', 'hello-elementor-child') . $e->getMessage();
	}

    return ob_get_clean();
}
add_shortcode('vstupenky_form', 'vstupenky_form');

/**
 * funkcia  pre vratene počtu tréningov
 */
function workshopQuantityScript() {
	$args = array(
		'post_type'      => 'product',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'meta_query'     => array(
			array(
				'key'        => '_stock',
				'type'       => 'numeric',
				'value'      => 0,
				'compare'    => '>',
			),
		),
		'tax_query'      => array(
			//filter pre kategoriu workshop
			array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => 'workshop',
			)
		)
	);

	$workshops_query = new WP_Query($args);
	$workshops = $workshops_query->get_posts();	
	
	echo '<script type="text/javascript">' . PHP_EOL;
	echo '	var workshopQuantity = {};' . PHP_EOL;

	foreach ($workshops as $workshop) {
		$workshop_object = wc_get_product($workshop->ID);

		$workshop_sku = $workshop_object->get_sku();
		$workshop_quantity = $workshop_object->get_stock_quantity();

		echo '	workshopQuantity['. $workshop_sku .'] = '. $workshop_quantity . ';' . PHP_EOL;
	}
	echo "</script>" . PHP_EOL;
}

/**
 * funkcia pre vygenerovanie vstupenkovych formulárov podla objednávky
 */
function vygenerujFormulare($cart_contents) {
    $pocet = 0;
	$order_id = 0;

    foreach ($cart_contents as $item) {
        $pocet += $item['quantity'];
		if($order_id === 0) {
			$order_id = isset($item['order_id']) ? $item['order_id'] : 0;
		}
    }

    echo '<div class="woocommerce-aditional-info">';

    if($pocet == 0) {
        //informácia pre editora stránky
        if(is_elementor_preview_mode() || is_elementor_edit_mode()) {
			echo '<span>' . esc_html__('There is no selected product in the cart. To generate ticket forms, at least one product must be added to the cart.', 'hello-elementor-child') . '</span>';
			echo '</div>';
        }
        return;
    }   

	workshopQuantityScript();

	//key - nazov premennej
	//value - cast z názvu custom field
	$fields = [
		'workshop_id' => 'workshop-id',
		'workshop_name' => 'workshop-name',
		'nazov_spolocnosti' => 'nazov-spolocnosti',
		'meno' => 'meno',
		'priezvisko' => 'priezvisko',
		'email' => 'email',
		'telefon' => 'telefon',
		'pracovna_pozicia' => 'pracovna-pozicia',
		'info_konferencia' => 'info-konferencia',
		'poznamka' => 'poznamka'
	];

    $counter = 0;	

	//flag, či zobrazujem formuláre v pokladni, alebo mimo pokladne
	$checkout = is_checkout() ? true : false;	

	if( ! $checkout ) {
		include_once('short-templates/vstupenky_form.php');
	}

    foreach ($cart_contents as $cart_item) {	
        $product_id = $cart_item['product_id'];
        $product = wc_get_product($product_id);

        //ziskaj vlastnosť miesto konania z produktu (e.g. Košice | Praha , ...)
        $miesto_konania = $product->get_attribute('pa_miesto-konania');

        //ziskaj rok konania konferencie z produktu (e.g. 2023 | 2024 , ...)
        $rok = $product->get_attribute('pa_rok');

        //ziskaj typ vstupenky z produktu (e.g. Combo Pass | Conference Pass | Training Pass )
        $typ_vstupenky = $product->get_attribute('pa_typ-vstupenky');

		//ziskaj dodatočný atribút povolit-zvolim-neskor
		//tento atribút slúži pre zobrazenie možnosti 'zvolím neskôr' podľa potreby projektu
		$zvolim_neskor = is_null($product->get_attribute('povolit-zvolim-neskor')) ? false : filter_var($product->get_attribute('povolit-zvolim-neskor'), FILTER_VALIDATE_BOOLEAN);

        //ziskaj vlastnosť konferencia z produktu (e.g. Košice 2024 | Praha 2024 , ...)
        $konferencia = $product->get_attribute('pa_konferencia');

		for ($i = 1; $i <= $cart_item['quantity']; $i++) {
			list(
				$meno,
				$priezvisko,
				$workshop_name,
				$workshop_id,
				$nazov_spolocnosti,
				$email,
				$telefon,
				$pracovna_pozicia,
				$info_konferencia,
				$poznamka
			) = array_fill(0, 10, "");
	
			$counter++;

			if (isset($cart_item['tickets']) && isset($cart_item['tickets'][$i])) {
				$ticket_data = $cart_item['tickets'][$i];
			} else {
				$ticket_data = [];
			}

			$edit_mode = isset($cart_item['edit_mode']) ? filter_var($cart_item['edit_mode'], FILTER_VALIDATE_BOOLEAN) : false;

			if (isset($ticket_data) && is_array($ticket_data)) {
				foreach ($fields as $var_name => $field_key) {
					$$var_name = isset($ticket_data[$field_key]) ? $ticket_data[$field_key] : "";
				}
			}

            echo "\r\n";
            include('short-templates/formular.php');
            echo "\r\n";
        }
    }

	if( ! $checkout ) {
		include('short-templates/vstupenky_buttons.php');
	}

    echo '</div>';	
}

/**
 * funkcia pre aktualizáciu vstupeniek
 * používateľská akcia v admin účte v objednávke v roletke
 */
function aktualizacia_obj_vstupenky(){
	if (!is_user_logged_in()) {
		wp_die(esc_html__('You must be logged in to save data.', 'hello-elementor-child'));
	}
	
	if (!isset($_POST['order_id'])) {
		wp_die(esc_html__('Missing order ID.', 'hello-elementor-child'));
	}
	
	$order_id = intval($_POST['order_id']);
    $order = wc_get_order($order_id);
    if (!$order || $order->get_user_id() !== get_current_user_id()) {
		wp_die(esc_html__('Order does not exist or you do not have permission to edit it.', 'hello-elementor-child'));
    }

	$order = wc_get_order($order_id);
	
	$counter = 0;

	foreach ($order->get_items() as $item_id => $item) {		
		for ($i = 1; $i <= $item->get_quantity(); $i++) {
			$counter++;
			$fieldsArray = array(	
				'vstupenka-' . $counter . '-workshop-id' => 'sanitize_text_field',
				'vstupenka-' . $counter . '-workshop-name' => 'sanitize_text_field',
				'vstupenka-' . $counter . '-email' => 'sanitize_email',
				'vstupenka-' . $counter . '-telefon' => 'sanitize_text_field',
				'vstupenka-' . $counter . '-pracovna-pozicia' => 'sanitize_text_field',
				'vstupenka-' . $counter . '-info-konferencia' => 'sanitize_textarea_field',
				'vstupenka-' . $counter . '-poznamka' => 'sanitize_textarea_field'
			);
			
			foreach($fieldsArray as $field_name => $sanitization_function){				
        		if (isset($_POST[$field_name])) {
					$sanitized_value = call_user_func($sanitization_function, $_POST[$field_name]);
					$order->update_meta_data($field_name, $sanitized_value);
        		}
			}
		}
    }
	
	$order->update_meta_data('workshops-update', 'true');
	$order->save();

	wp_redirect(add_query_arg('status', 'success', '/my-account/tickets-dashboard/'));
    exit;
}
add_action('admin_post_aktualizacia_obj_vstupenky', 'aktualizacia_obj_vstupenky');
add_action('admin_post_nopriv_aktualizacia_obj_vstupenky', 'aktualizacia_obj_vstupenky');

/**
 * pridanie checkboxu gdpr pri checkoute 
 */
function add_privacy_policy_checkbox() {
	$privacy_policy_text = trim( wc_get_privacy_policy_text('checkout') );

    woocommerce_form_field('privacy_policy', array(
        'type'          => 'checkbox',
        'class'         => array('privacy'),
        'label_class'   => array('woocommerce-form__label woocommerce-form__label-for-checkbox'),
        'input_class'   => array('woocommerce-form__input woocommerce-form__input-checkbox'),
        'required'      => true,
        'label'         => __($privacy_policy_text, 'hello-elementor-child'),
    ));
}
add_action( 'woocommerce_review_order_before_submit', 'add_privacy_policy_checkbox', 9 );

/**
 * uloženie súhlasu s ochranou osobných údajov po vytvorení objednávky
 */
function save_gdpr_meta($order_id) {
	if (!isset($_POST['privacy_policy']) || $_POST['privacy_policy'] === '') {
		update_post_meta($order_id, 'gdpr_accepted', 'no');
	} else {
		update_post_meta($order_id, 'gdpr_accepted', 'yes');
	}

	update_post_meta($order_id, 'gdpr_acceptation_date', date("Y-m-d H:i:s"));
}
add_action('woocommerce_checkout_update_order_meta', 'save_gdpr_meta');

/**
 * uloženie súhlasu s obchodnými podmienkami po vytvorení objednávky
 */
function save_terms_meta($order_id) {
	if (!isset($_POST['terms-field']) || $_POST['terms-field'] === '') {
		update_post_meta($order_id, 'terms_accepted', 'no');
	} else {
		update_post_meta($order_id, 'terms_accepted', 'yes');
	}

	update_post_meta($order_id, 'terms_acceptation_date', date("Y-m-d H:i:s"));
}
add_action('woocommerce_checkout_update_order_meta', 'save_terms_meta');

/**
 * Validácia súhlasu s ochranou osobných údajov
 */
function privacy_policy_validation() {
	if (!isset($_POST['privacy_policy']) || $_POST['privacy_policy'] === '') {
		wc_add_notice(__('Your consent to the processing of personal data is required to complete the order', 'hello-elementor-child'), 'error');
	}	
}
add_action('woocommerce_checkout_process', 'privacy_policy_validation');

/**
 * validácia vstupných políčk v checkoute
 */
function custom_form_validation() {
    $cart_contents = WC()->cart->get_cart();
	$counter = 0;

    foreach ($cart_contents as $cart_item) {
        for ($i = 1; $i <= $cart_item['quantity']; $i++) {
			$counter++;
			if (!isset($_POST['vstupenka-' . $counter . '-typ-listku-name']) || $_POST['vstupenka-' . $counter . '-typ-listku-name'] === '') {
				wc_add_notice(
					sprintf(
						__('<li>field: <b>Participant %d - Ticket type</b> is required', 'hello-elementor-child'),
						$counter
					),
					'error'
				);
			}
			
			if (!isset($_POST['vstupenka-' . $counter . '-workshop-id']) || $_POST['vstupenka-' . $counter . '-workshop-id'] === '') {
				wc_add_notice(
					sprintf(
						__('<li>field: <b>Participant %d - Training</b> is required', 'hello-elementor-child'),
						$counter
					),
					'error'
				);
			}
			
			if (!isset($_POST['vstupenka-' . $counter . '-nazov-spolocnosti']) || $_POST['vstupenka-' . $counter . '-nazov-spolocnosti'] === '') {
				wc_add_notice(
					sprintf(
						__('<li>field: <b>Participant %d - Company name</b> is required', 'hello-elementor-child'),
						$counter
					),
					'error'
				);
			}
			
			if (!isset($_POST['vstupenka-' . $counter . '-meno']) || $_POST['vstupenka-' . $counter . '-meno'] === '') {
				wc_add_notice(
					sprintf(
						__('<li>field: <b>Participant %d - First name</b> is required', 'hello-elementor-child'),
						$counter
					),
					'error'
				);
			}
			
			if (!isset($_POST['vstupenka-' . $counter . '-priezvisko']) || $_POST['vstupenka-' . $counter . '-priezvisko'] === '') {
				wc_add_notice(
					sprintf(
						__('<li>field: <b>Participant %d - Last name</b> is required', 'hello-elementor-child'),
						$counter
					),
					'error'
				);
			}
			
			if (!isset($_POST['vstupenka-' . $counter . '-email']) || $_POST['vstupenka-' . $counter . '-email'] === '') {
				wc_add_notice(
					sprintf(
						__('<li>field: <b>Participant %d - Email</b> is required', 'hello-elementor-child'),
						$counter
					),
					'error'
				);
			}
			
		}
    }
}
add_action('woocommerce_checkout_process', 'custom_form_validation');

/**
 * ulozenie custom fields po vytvorení objednávky 
 */
function custom_save_order_custom_fields($order_id) {
    $order = wc_get_order($order_id);
	
	$counter = 0;
	
	foreach ($order->get_items() as $item_id => $item) {		
		for ($i = 1; $i <= $item->get_quantity(); $i++) {
			$counter++;
			$fieldsArray = array(	
				'vstupenka-' . $counter . '-typ-listku-id',
				'vstupenka-' . $counter . '-typ-listku-name',
				'vstupenka-' . $counter . '-workshop-id',
				'vstupenka-' . $counter . '-workshop-name',
				'vstupenka-' . $counter . '-nazov-spolocnosti',
				'vstupenka-' . $counter . '-meno',
				'vstupenka-' . $counter . '-priezvisko',
				'vstupenka-' . $counter . '-email',
				'vstupenka-' . $counter . '-telefon',
				'vstupenka-' . $counter . '-pracovna-pozicia',
				'vstupenka-' . $counter . '-info-konferencia',
				'vstupenka-' . $counter . '-poznamka'
			);
			
			foreach($fieldsArray as $field_name){				
        		if (isset($_POST[$field_name])) {
            		$value = sanitize_text_field($_POST[$field_name]);
            		update_post_meta($order_id, $field_name, $value);
        		}
			}
		}
    }

	update_post_meta($order_id, $field_name, $value);

}
add_action('woocommerce_checkout_update_order_meta', 'custom_save_order_custom_fields');

/**
 * funckia ziska info o vsetkych tréningoch podľa zvolenej konferencie 
 */
function getWorkshops($konferencia) {
	$args = array(
		'post_type'      => 'product',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'meta_query'     => array(
			array(
				'key'        => '_stock',
				'type'       => 'numeric',
				'value'      => 0,
				'compare'    => '>',
			),
		),
		'tax_query'      => array(
			'relation'   => 'AND',
			//filter pre kategoriu tréningu
			array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => 'workshop',
			),
			//filter pre vlastnosti tréningu
			array(
				'taxonomy' => 'pa_konferencia',
				'field'    => 'name',
				'terms'    => $konferencia,
				'operator' => 'IN'
			)
		)
	);

	$workshops_query = new WP_Query($args);
	$workshops = $workshops_query->get_posts();

	return $workshops;
}

/**
 * short code pre zobrazenie custom fields pre objednavku 
 */
function custom_fields_objednavka($atts = [], $content = null, $tag = '') {
	ob_start();

	// normalize attribute keys, lowercase	
	$atts = array_change_key_case( (array) $atts, CASE_LOWER );

	// override default attributes with user attributes
	$user_atts = shortcode_atts( 
		array (),
		$atts,
		$tag
	);
        
	if (!isset($atts['orderid'])) {
		echo '<span>' . esc_html__('Enter the order number in the shortcode using the attribute <b>orderid</b>.', 'hello-elementor-child') . '</span>';
		return;
	}
	
    $order_id = $atts['orderid'];
    $order = wc_get_order($order_id);
	
    echo "<table>\n";
    echo "  <tr>\n";
    echo "      <th>Key</th>\n";
    echo "      <th>Value</th>\n";
    echo "  </tr>\n";

	$counter = 0;
	
	foreach ($order->get_items() as $item_id => $item) {
		for ($i = 1; $i <= $item->get_quantity(); $i++) {
			$counter++;
			$fieldsArray = array(	
				'vstupenka-' . $counter . '-typ-listku-id',
				'vstupenka-' . $counter . '-typ-listku-name',
				'vstupenka-' . $counter . '-workshop-id',
				'vstupenka-' . $counter . '-workshop-name',
				'vstupenka-' . $counter . '-nazov-spolocnosti',
				'vstupenka-' . $counter . '-meno',
				'vstupenka-' . $counter . '-priezvisko',
				'vstupenka-' . $counter . '-email',
				'vstupenka-' . $counter . '-telefon',
				'vstupenka-' . $counter . '-pracovna-pozicia',
				'vstupenka-' . $counter . '-info-konferencia',
				'vstupenka-' . $counter . '-poznamka'
			);
			
			foreach($fieldsArray as $field_name) {
                $value = sanitize_text_field($_POST[$field_name]);            		
                echo "  <tr>\n";
                echo "      <td>" . $field_name . "</td>\n";
                echo "      <td>" . $value . "</td>\n";
                echo "  </tr>\n";
			}
		}
    }

    echo "</table>\n";

	return ob_get_clean();
}
add_shortcode('cf_objednavka', 'custom_fields_objednavka');

/**
 * zmena labels v checkoute 
 */
function change_checkout_labels_text( $translated_text, $text, $domain ) {
	switch($text) {
		case 'Subtotal':
			$translated_text = __('Total excl. VAT', 'hello-elementor-child');
			break;

		case 'Total':
			$translated_text = __('Total incl. VAT', 'hello-elementor-child');
			break;
		default:
	}
	
	return $translated_text;	
}
add_filter( 'gettext', 'change_checkout_labels_text', 20, 3 );

/**
 * porovnanie dátumu radenie ASC 
 */
function compare_dates_asc($a, $b) {
    return strtotime($a['start']) - strtotime($b['start']);
}

/**
 * porovnanie dátumu radenie DESC 
 */
function compare_dates_desc($a, $b) {
    return strtotime($b['start']) - strtotime($a['start']);
}

/**
 * shortcode pre vygenerovanie s cenovej ponuky pre vstupenky podľa časového hľadiska 
 */
function shortcode_cena_vstupenky($atts = [], $content = null, $tag = '') {
	ob_start();

	// normalize attribute keys, lowercase	
	$atts = array_change_key_case( (array) $atts, CASE_LOWER );

	// override default attributes with user attributes
	$user_atts = shortcode_atts( 
		array (
            'product-id' => 0,
			'early-od' => '2024-01-01',
            'early-do' => '2024-09-17',
            'early-cena' => 0,           
			'regular1-od' => '2024-09-18',
            'regular1-do' => '2024-09-30',
            'regular1-cena' => 0,
            'late-od' => '2024-10-01',
            'late-do' => '2024-10-10',
            'late-cena' => 0,            
            'actual-date' => date("Y-m-d H:i:s")
        ),
		$atts,
		$tag
	);

    $aktualny_cas = $user_atts['actual-date'];	
	$terminy = array();

	if ($aktualny_cas >= $user_atts['regular1-od'] && $aktualny_cas <= $user_atts['regular1-do']) {
		$terminy[] = array(
			"name" => "Regular price",
			"start" => $user_atts['regular1-od'] . ' 00:00:00',
			"end" => $user_atts['regular1-do'] . ' 23:59:59',
			"price" => $user_atts['regular1-cena']
		);
	} else {
		if (isset($atts['regular2-od']) && isset($atts['regular2-do']) && isset($atts['regular2-cena'])) {
			$terminy[] = array(
				"name" => "Regular price",
				"start" => $atts['regular2-od'] . ' 00:00:00',
				"end" => $atts['regular2-do'] . ' 23:59:59',
				"price" => $atts['regular2-cena']
			);
		}
	}

	$terminy[] = array(
		"name" => "Early Bird",
		"start" => $user_atts['early-od'] . ' 00:00:00',
		"end" => $user_atts['early-do'] . ' 23:59:59',
		"price" => $user_atts['early-cena']
	);

	$terminy[] = array(
		"name" => "Late price",
		"start" => $user_atts['late-od'] . ' 00:00:00',
		"end" => $user_atts['late-do'] . ' 23:59:59',
		"price" => $user_atts['late-cena']
	);

    usort($terminy, 'compare_dates_asc');

    $aktualny_termin = null;
    foreach ($terminy as $termin) {
        if ($termin['start'] <= $aktualny_cas && $aktualny_cas <= $termin['end']) {
            $aktualny_termin = $termin;
            break;
        }
    }

    $zvysne_terminy = array();
    foreach ($terminy as $termin) {
        if ($termin != $aktualny_termin) {
            $zvysne_terminy[] = $termin;
        }
    }
    usort($zvysne_terminy, 'compare_dates_asc');

    if($aktualny_termin == null) {
		$prvy_termin = $terminy[0];
		$posledny_termin = $terminy[count($terminy) - 1];

		if (($prvy_termin['start'] . ' 00:00:00') > $aktualny_cas) {
			$sprava = esc_html__('The product is not yet on sale.', 'hello-elementor-child');
		} else if (($posledny_termin['end'] . ' 23:59:59') < $aktualny_cas) {
			$sprava = esc_html__('The product is unfortunately no longer available for purchase.', 'hello-elementor-child');
		}
		
        echo "<div>";
        echo "  <span><b>" . $sprava . "</b></span>";
        echo "</div>";
    } else {
        include('short-templates/cena_vstupenky.php');
    }

    return ob_get_clean();
}
add_shortcode('cena_vstupenky', 'shortcode_cena_vstupenky');

/**
 * customizované pridávanie produktov do košíku AJAX 
 */
function woocommerce_ajax_add_to_cart() {
    $product_id = apply_filters('woocommerce_add_to_cart_product_id', absint($_POST['product_id']));
    $quantity = empty($_POST['quantity']) ? 1 : wc_stock_amount($_POST['quantity']);
	$passed_validation = apply_filters('custom_add_to_cart_validation', true, $product_id, $quantity);
	$product_status = get_post_status($product_id);

	if ($passed_validation && WC()->cart->add_to_cart($product_id, $quantity) && 'publish' === $product_status) {

		do_action('woocommerce_ajax_added_to_cart', $product_id);

		if ('yes' === get_option('woocommerce_cart_redirect_after_add')) {
			wc_add_to_cart_message(array($product_id => $quantity), true);
		}

		WC_AJAX :: get_refreshed_fragments();
	} else {

		$data = array(
			'error' => true,
			'product_url' => apply_filters('woocommerce_cart_redirect_after_error', get_permalink($product_id), $product_id));

		echo wp_send_json($data);
	}

	wp_die();
}
add_action('wp_ajax_woocommerce_ajax_add_to_cart', 'woocommerce_ajax_add_to_cart');
add_action('wp_ajax_nopriv_woocommerce_ajax_add_to_cart', 'woocommerce_ajax_add_to_cart');

/**
 * validácia pridaného tovaru pri vložení do košíku
 */
function custom_add_to_cart_validation($passed, $product_id, $quantity) {
    return $passed;
}
remove_filter('woocommerce_add_to_cart_validation', 'woocommerce_add_to_cart_validation_function', 10, 3);
add_filter('woocommerce_add_to_cart_validation', 'custom_add_to_cart_validation', 10, 3);

/**
 * funkcia pre pridanie custom css triedy po prihlásení sa
 */
function logged_in_filter($classes) {
	if( is_user_logged_in() ) {
		$classes[] = 'logged-in-condition';
	} else {
		$classes[] = 'logged-out-condition';
	}

	return $classes;
}
add_filter('body_class','logged_in_filter');

/**
 * override pre štandardnú adresu
 */
function custom_override_default_address_fields( $fields ) {
	unset( $fields[ 'address_2' ] );
	unset( $fields[ 'state' ] );

	//system field
	$fields['company']['label'] = __('Company name', 'hello-elementor-child');
	$fields['company']['placeholder'] = __('Company name', 'hello-elementor-child');
	$fields['company']['class'] = array(
		'form-row-first'
	);
	$fields['company']['required'] = true;
	
	//system field
	$fields['first_name']['label'] = __('First name', 'hello-elementor-child');
	$fields['first_name']['placeholder'] = __('First name', 'hello-elementor-child');
	$fields['first_name']['class'] = array(
		'form-row-last'
	);
	$fields['first_name']['required'] = true;

	//system field
	$fields['last_name']['label'] = __('Last name', 'hello-elementor-child');
	$fields['last_name']['placeholder'] = __('Last name', 'hello-elementor-child');
	$fields['last_name']['class'] = array(
		'form-row-first'
	);
	$fields['last_name']['required'] = true;
	
	//system field
	$fields['address_1']['label'] = __('Street and number', 'hello-elementor-child');
	$fields['address_1']['placeholder'] = __('Street and number', 'hello-elementor-child');
	$fields['address_1']['class'] = array(
		'form-row-last'
	);
	$fields['address_1']['required'] = true;

	//system field
	$fields['city']['label'] = __('City', 'hello-elementor-child');
	$fields['city']['placeholder'] = __('City', 'hello-elementor-child');
	$fields['city']['class'] = array(
		'form-row-first'
	);
	$fields['city']['required'] = true;

	//system field
	$fields['postcode']['label'] = __('Post code', 'hello-elementor-child');
	$fields['postcode']['placeholder'] = __('Post code', 'hello-elementor-child');
	$fields['postcode']['class'] = array(
		'form-row-last'
	);
	$fields['postcode']['required'] = true;
	
	//system field
	$fields['country']['label'] = __('Country', 'hello-elementor-child');
	$fields['country']['placeholder'] = __('Select option', 'hello-elementor-child');
	$fields['country']['class'] = array(
		'form-row-first',
		'dropdown-full-size'
	);
	$fields['country']['required'] = true;	
		
	$fields['company']['priority'] = 10;
	$fields['first_name']['priority'] = 40;
	$fields['last_name']['priority'] = 45;
	$fields['address_1']['priority'] = 50;
	$fields['city']['priority'] = 64;
	$fields['postcode']['priority'] = 65;
	$fields['country']['priority'] = 70;

	return $fields;
}
add_filter('woocommerce_default_address_fields', 'custom_override_default_address_fields', 20);

/**
 * override pre billing adresu
 */
function custom_override_default_billing_fields( $fields ) {
	unset( $fields[ 'billing_address_2' ] );
	unset( $fields[ 'billing_state' ] );

	//custom field
	//show in email - false
	//show in order detail page - true
	$fields['billing_bussiness_industry']['label'] = esc_html__('Industry', 'hello-elementor-child');
	$fields['billing_bussiness_industry']['placeholder'] = esc_html__('Select an option', 'hello-elementor-child');
	$fields['billing_bussiness_industry']['type'] = 'select';
	$fields['billing_bussiness_industry']['class'] = array(
		'form-row-last',
		'dropdown-full-size'
	);
	$fields['billing_bussiness_industry']['required'] = false;
	$fields['billing_bussiness_industry']['options'] = array(
		'' => esc_html__('Select an option', 'hello-elementor-child'),
		'Akadémia' => esc_html__('Academy', 'hello-elementor-child'),
		'Elektrina/ropa/plyn' => esc_html__('Electricity/Oil/Gas', 'hello-elementor-child'),
		'Financie' => esc_html__('Finance', 'hello-elementor-child'),
		'Štátna správa' => esc_html__('Government', 'hello-elementor-child'),
		'IT/Softvér' => esc_html__('IT/Software', 'hello-elementor-child'),
		'Výroba/maloobchod' => esc_html__('Manufacturing/Retail', 'hello-elementor-child'),
		'Vojenská oblasť' => esc_html__('Military Sector', 'hello-elementor-child'),
		'Nezisková oblasť' => esc_html__('Non-Profit Sector', 'hello-elementor-child'),
		'Farmaceutické a zdravotnícke zariadenia' => esc_html__('Pharmaceuticals and Healthcare', 'hello-elementor-child'),
		'Telekom' => esc_html__('Telecom', 'hello-elementor-child'),
		'Doprava' => esc_html__('Transportation', 'hello-elementor-child'),
		'Ostatné' => esc_html__('Other', 'hello-elementor-child')
	);

	//custom field
	//show in email - false
	//show in order detail page - true
	$fields['billing_bussiness_number_of_employees']['label'] = esc_html__('Number of employees', 'hello-elementor-child');
	$fields['billing_bussiness_number_of_employees']['placeholder'] = esc_html__('Select on option', 'hello-elementor-child');
	$fields['billing_bussiness_number_of_employees']['type'] = 'select';
	$fields['billing_bussiness_number_of_employees']['class'] = array(
		'form-row-first',
		'dropdown-full-size'
	);
	$fields['billing_bussiness_number_of_employees']['required'] = false;
	$fields['billing_bussiness_number_of_employees']['options'] = array(
		'' => esc_html__('Select an option', 'hello-elementor-child'),
		'1 - 10 zamestnancov' => esc_html__('1 - 10 employees', 'hello-elementor-child'),
		'11 - 50 zamestnancov' => esc_html__('11 - 50 employees', 'hello-elementor-child'),
		'51 - 100 zamestnancov' => esc_html__('51 - 100 employees', 'hello-elementor-child'),
		'101 - 1000 zamestnancov' => esc_html__('101 - 1000 employees', 'hello-elementor-child'),
		'1001 - 5000 zamestnancov' => esc_html__('1001 - 5000 employees', 'hello-elementor-child'),
		'>5000 zamestnancov' => esc_html__('>5000 employees', 'hello-elementor-child')
	);

	//system field
	$fields['billing_email']['label'] = esc_html__('Email', 'hello-elementor-child');
	$fields['billing_email']['placeholder'] = esc_html__('Email', 'hello-elementor-child');
	$fields['billing_email']['class'] = array(
		'form-row-last'
	);
	$fields['billing_email']['required'] = true;

	//system field
	$fields['billing_phone']['label'] = esc_html__('Phone number', 'hello-elementor-child');
	$fields['billing_phone']['placeholder'] = esc_html__('Phone number', 'hello-elementor-child');
	$fields['billing_phone']['class'] = array(
		'form-row-first'
	);
	$fields['billing_phone']['required'] = false;

	//custom field
	//show in email - false
	//show in order detail page - true
	$fields['billing_bussiness_ico']['label'] = esc_html__('Company ID', 'hello-elementor-child');
	$fields['billing_bussiness_ico']['placeholder'] = esc_html__('Company ID', 'hello-elementor-child');
	$fields['billing_bussiness_ico']['type'] = 'text';
	$fields['billing_bussiness_ico']['class'] = array(
		'form-row-last'
	);
	$fields['billing_bussiness_ico']['required'] = false;

	//custom field
	//show in email - false
	//show in order detail page - true
	$fields['billing_bussiness_dic']['label'] = esc_html__('Tax ID', 'hello-elementor-child');
	$fields['billing_bussiness_dic']['placeholder'] = esc_html__('Tax ID', 'hello-elementor-child');
	$fields['billing_bussiness_dic']['type'] = 'text';
	$fields['billing_bussiness_dic']['class'] = array(
		'form-row-first'
	);
	$fields['billing_bussiness_dic']['required'] = false;

	//custom field
	//show in email - false
	//show in order detail page - true
	$fields['billing_bussiness_ic_dph']['label'] = esc_html__('Vat ID', 'hello-elementor-child');
	$fields['billing_bussiness_ic_dph']['placeholder'] = esc_html__('Vat ID', 'hello-elementor-child');
	$fields['billing_bussiness_ic_dph']['type'] = 'text';
	$fields['billing_bussiness_ic_dph']['class'] = array(
		'form-row-last'
	);
	$fields['billing_bussiness_ic_dph']['required'] = false;


	$fields['billing_company']['priority'] = 10;
	$fields['billing_bussiness_industry']['priority'] = 20;
	$fields['billing_bussiness_number_of_employees']['priority'] = 30;
	$fields['billing_first_name']['priority'] = 40;
	$fields['billing_last_name']['priority'] = 45;
	$fields['billing_email']['priority'] = 46;
	$fields['billing_phone']['priority'] = 47;
	$fields['billing_address_1']['priority'] = 50;
	$fields['billing_city']['priority'] = 64;
	$fields['billing_postcode']['priority'] = 65;
	$fields['billing_country']['priority'] = 70;
	$fields['billing_bussiness_ico']['priority'] = 75;
	$fields['billing_bussiness_dic']['priority'] = 80;
	$fields['billing_bussiness_ic_dph']['priority'] = 85;

	return $fields;
}
add_filter( 'woocommerce_billing_fields' , 'custom_override_default_billing_fields', 20 );

/**
 * override pre shipping adresu
 */
function custom_override_default_shipping_fields( $fields ) {
	$fields['shipping_postcode']['class'] = array(
		'form-row-first'
	);

	$fields['shipping_city']['class'] = array(
		'form-row-last'
	);
	$fields['shipping_country']['class'] = array(
		'form-row-wide'
	);
	
	$fields['shipping_postcode']['priority'] = 65;
	$fields['shipping_city']['priority'] = 66;	

	return $fields;
}
add_filter( 'woocommerce_shipping_fields' , 'custom_override_default_shipping_fields', 20 );

/**
 * override pre políčka v checkoute
 */
function custom_override_other_custom_checkout_fields( $fields ) {
	//custom field
	//show in email - false
	//show in order detail page - true
	$fields['billing']['billing_business_order_number']['label'] = esc_html__('Order Purchase Number', 'hello-elementor-child');
	$fields['billing']['billing_business_order_number']['placeholder'] = esc_html__('Order Purchase Number', 'hello-elementor-child');
	$fields['billing']['billing_business_order_number']['type'] = 'text';
	$fields['billing']['billing_business_order_number']['class'] = array(
		'form-row-wide'
	);
	$fields['billing']['billing_business_order_number']['required'] = false;
	$fields['billing']['billing_business_order_number']['priority'] = 90;

	return $fields;
}
add_filter( 'woocommerce_checkout_fields' , 'custom_override_other_custom_checkout_fields', 20 );

/**
 * zobrazenie checkout políčok v checkoute
 */
function display_custom_checkout_fields_in_order($order) {
    $order_id = $order->get_id();
    
	$fields_to_display = array(
        '_billing_bussiness_industry' => __('Industry', 'hello-elementor-child'),
        '_billing_bussiness_number_of_employees' => __('Number of Employees', 'hello-elementor-child'),
        '_billing_bussiness_ico' => __('Company ID', 'hello-elementor-child'),
        '_billing_bussiness_dic' => __('Tax ID', 'hello-elementor-child'),
        '_billing_bussiness_ic_dph' => __('Vat ID', 'hello-elementor-child'),
        '_billing_business_order_number' => __('Order Purchase Number', 'hello-elementor-child'),
    );

    $order_meta = get_post_meta($order_id);

    echo '<table class="woocommerce-table woocommerce-table--custom-fields shop_table custom-fields">';

    foreach ($fields_to_display as $field_key => $field_label) {
        if (isset($order_meta[$field_key])) {
            $meta_value = $order_meta[$field_key][0];
            echo '<tr>';
            echo '	<th>' . esc_html($field_label) . '</th>';
            echo '	<td>' . esc_html($meta_value) . '</td>';
            echo '</tr>';
        }
    }

    echo '</table>';
}
add_action('woocommerce_order_details_after_order_table', 'display_custom_checkout_fields_in_order');

/**
 * customizácia login do wordpressu
 * pre administráciu
 */
function custom_wordpress_login_css() {
	echo 
	'<style type="text/css">
		body {
			background: #FAFAFA;
		}
		
		.login form {
			background: #D9D9D94D;
			border-radius: 20px;
			border: none;
			min-width: 300px;
		}
		
		.login label {
			font-family: "Gilroy medium", Open sans;
			font-size: 14px;
			font-weight: 400;
			letter-spacing: 0.4px;
			padding-left: 10px;
			color: #7A7B7B;
		}
		
		.login h1 a {
    		background-image: none, url(/wp-content/uploads/2022/05/LOGO.svg);
    		background-size: contain;
    		background-position: center top;
    		background-repeat: no-repeat;
			width: 100%;
			height: 70px;
		}
		
		.login form .input, .login input[type="text"], .login input[type="password"] {
    		font-size: 24px;
    		line-height: 1.33333333;
			border-radius: 10px;
			border: none;
			padding: 5px 10px 5px 10px;
		}
		
		.wp-core-ui select {
			font-size: 14px;
			line-height: 2;
			color: #2c3338;
			border-color: #8c8f94;
			box-shadow: none;
			border-radius: 10px;
			padding: 5px 24px 5px 10px;
			margin-right: 5px;
			min-height: 30px;
			max-width: 25rem;
		}

		.wp-core-ui .button-primary, .wp-core-ui .button {
    		background: #224F5A;
			border-radius: 10px;
			border: none;
    		color: white;
			padding: 5px 20px 5px 20px !important;
			font-family: "Gilroy medium", Open sans;
			font-size: 14px;
			letter-spacing: 0.4px;
		}
		
		.wp-core-ui .button-primary:hover, .wp-core-ui .button:hover {
    		background: #00BD9C;
			border: none;
    		color: white;
		}
	</style>';
}
add_action('login_head', 'custom_wordpress_login_css');

/**
 * pridanie vlastnej akcie do rozbaľovacieho menu objednávky
 * pregenerovanie faktúr v superfaktúre + aktualizácia tasku v asana
 */
function pridanie_akcie_do_objednavky_prenos_do_sf_asana($actions) {
    $actions['vlastna_akcia_prenos_do_sf_asana'] = __('Transfer to SF + ASANA', 'hello-elementor-child');
    return $actions;
}
add_filter('woocommerce_order_actions', 'pridanie_akcie_do_objednavky_prenos_do_sf_asana');

/**
 * pridanie vlastnej akcie do rozbaľovacieho menu objednávky
 * aktualizácia task description v asana
 */
function pridanie_akcie_do_objednavky_aktualizacia_description_v_asana($actions) {
    $actions['vlastna_akcia_aktualizacia_description_asana'] = __('Update ASANA description', 'hello-elementor-child');
    return $actions;
}
add_filter('woocommerce_order_actions', 'pridanie_akcie_do_objednavky_aktualizacia_description_v_asana');

/**
 * pridanie custom polí do objednávky
 */
function set_custom_field_in_order($order_id, $custom_field_id, $custom_field_value) {
	$order = wc_get_order($order_id);

    if ($order) {
        // Kontrola, či vlastné pole existuje
        $custom_field = $order->get_meta($custom_field_id);

        // Ak vlastné pole neexistuje, vytvor ho
        if (empty($custom_field)) {
            $order->add_meta_data($custom_field_id, $custom_field_value);
        } else {
            // Ak vlastné pole existuje, aktualizuj ho
            $order->update_meta_data($custom_field_id, $custom_field_value);
        }

        $order->save();
    } else {
        error_log(__("Order with nr: ", 'hello-elementor-child') . $order_id . " not found.");
    }
}

/**
 * spracovanie akcie a aktualizácia vlastného poľa
 */
function prenos_dat_do_superfaktury_asany($order_id) {
	set_custom_field_in_order($order_id, 'sf_update', 'true');
}
add_action('woocommerce_order_action_vlastna_akcia_prenos_do_sf_asana', 'prenos_dat_do_superfaktury_asany');

/**
 *  Spracovanie akcie a aktualizácia vlastného poľa 
 */
function aktualizacia_asana_task_description($order_id) {
	set_custom_field_in_order($order_id, 'asana_update', 'true');
}
add_action('woocommerce_order_action_vlastna_akcia_aktualizacia_description_asana', 'aktualizacia_asana_task_description');

/**
 * debug informácia na výstup
 */
function debug_info($preheading, $heading, $variable) {
	echo '<h5>' . $preheading . ':' . $heading . '</h5>';
	echo '<pre>' . json_encode($variable, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>';
}