<?php
/*
Plugin Name: Telegram Bot & Channel (Custom)
Description: My Custom Telegram Plugin
Author: My name
Version: 1
*/

add_action('telegram_parse','telegramcustom_parse', 10, 2);

function telegramcustom_parse( $telegram_user_id, $text ) {

    $plugin_post_id = telegram_getid( $telegram_user_id );

    if ( !$plugin_post_id ) {
        return;
    }

    if ( $telegram_user_id == '63480147' || $telegram_user_id == '61359892' || $telegram_user_id == '166538229' ) {
      if ( $text == 'ok' ) {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'segnalazioni',
            array(
                'stato' => 1
            ),
            array(
                'stato' => '0'
            ),
            array( '%d', '%d' )
        );
        telegram_sendmessage( $telegram_user_id, 'Approvato tutto. Digita d:ID se vuoi rimuovere qualcosa');
        die();
      } else if ( substr( $text, 0, 1 ) === 'd' ) { //as:id:issue
        $arr = explode(':', $text);
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'segnalazioni',
            array(
                'stato' => 2
            ),
            array(
                'id' => $arr[1]
            ),
            array( '%d', '%d' )
        );
        telegram_sendmessage( $telegram_user_id, $arr[1].' eliminato');
        die();
      } else if ( substr( $text, 0, 2 ) === 'as' ) { //as:id:issue

        $arr = explode(':', $text);

        global $wpdb;

        $ob = $wpdb->get_row('SELECT * FROM '.$wpdb->prefix.'segnalazioni WHERE id="'.$arr[1].'"');

        if ( $ob->github_issue ) {
          telegram_sendmessage( $telegram_user_id, 'issue già creata'.PHP_EOL.'http://goo.gl/zdByBS');
          die();
        }

        $title = $ob->description;
        $body = '!['.$ob->description.']('.$ob->url.')'.PHP_EOL.'[Link Mappa](http://www.piersoft.it/terremotocentro/#7/'.$ob->lat.'/'.$ob->lon.')';

        $data = array(
          'title' => $title,
          'body' => $body
        );

        $options = array(
          'http' => array(
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n"."Authorization: Basic ".base64_encode( "milesimarco:MY_PERSONAL_TOKEN" )."\r\n",
            'content' => json_encode( $data )
            )
        );

        $context  = stream_context_create( $options );
        ini_set("user_agent","Opera/9.80 (Windows NT 6.1; U; Edition Campaign 21; en-GB) Presto/2.7.62 Version/11.00");
        $result = file_get_contents( 'https://api.github.com/repos/emergenzeHack/terremotocentro_segnalazioni/issues', false, $context);

        $array_response = (array)json_decode($result, TRUE);
        $url_issue = $array_response['html_url'];

        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'segnalazioni',
            array(
                'github_issue' => $url_issue
            ),
            array(
                'id' => $arr[1]
            ),
            array( '%s' ),
            array( '%d')
        );

        telegram_sendmessage( $telegram_user_id, 'issue creata'.PHP_EOL.'http://goo.gl/zdByBS');
        die();
      }
    }

    if ( $text == 'Invia' || $text == 'invia' ) {
      $desc = get_post_meta( $plugin_post_id, 'telegram_custom_description', true );

      $lat = get_post_meta( $plugin_post_id, 'telegram_last_latitude', true );
      $long = get_post_meta( $plugin_post_id, 'telegram_last_longitude', true );
      if ( !$lat > 0 || !$long > 0 ) {
        telegram_sendmessage( $telegram_user_id, 'Tramite la 📎 invia per prima cosa la tua posizione');
        return;
      }

      if ( !$desc ) {
        telegram_sendmessage( $telegram_user_id, 'Errore. Tramite la 📎 invia per prima cosa la tua posizione');
      }
      get_post_meta($plugin_post_id, 'telegram_username', true) ? $t_user = get_post_meta($plugin_post_id, 'telegram_username', true) : $t_user = '';

      global $wpdb;
      $wpdb->insert(
          $wpdb->prefix . 'segnalazioni',
          array(
              'lat' => $lat,
              'lon' => $long,
              'telegram_id' => $telegram_user_id,
              'telegram_username' => $t_user,
              'wordpress_id' => $plugin_post_id,
              'description' => $desc
          ),
          array( '%s', '%s', '%d', '%s', '%d', '%s' )
      );
      delete_post_meta( $plugin_post_id, 'telegram_custom_description' );
      delete_post_meta( $plugin_post_id, 'telegram_custom_state' );
      delete_post_meta( $plugin_post_id, 'telegram_last_latitude' );
      delete_post_meta( $plugin_post_id, 'telegram_last_longitude' );

      telegram_sendmessage( $telegram_user_id, 'Segnalazione registrata. Grazie');

      telegramcustom_send_check( $wpdb->insert_id, '', $desc );

    } else if ( $text == 'info' || $text == '/help' ) {
      telegram_sendmessage( $telegram_user_id, 'Bot creato per http://terremotocentroitalia.info/'.PHP_EOL.'Per info e suggerimenti: @Milmor');
      delete_post_meta( $plugin_post_id, 'telegram_custom_description' );
      delete_post_meta( $plugin_post_id, 'telegram_custom_state' );
      delete_post_meta( $plugin_post_id, 'telegram_last_latitude' );
      delete_post_meta( $plugin_post_id, 'telegram_last_longitude' );
    } else if ( $text == 'mappa' ) {
      telegram_sendmessage( $telegram_user_id, 'http://goo.gl/24YcqW');
      delete_post_meta( $plugin_post_id, 'telegram_custom_description' );
      delete_post_meta( $plugin_post_id, 'telegram_custom_state' );
      delete_post_meta( $plugin_post_id, 'telegram_last_latitude' );
      delete_post_meta( $plugin_post_id, 'telegram_last_longitude' );
    } else if ( $text == '/stato' || $text == 'stato' ) {
      global $wpdb;
      $conteggio = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}segnalazioni WHERE telegram_id=".$telegram_user_id.";" );
      telegram_sendmessage( $telegram_user_id, 'Hai inviato '.$conteggio.' segnalazioni.'.PHP_EOL.'http://goo.gl/zdByBS');
    } else if ( $text == '/segnala' || $text == 'segnala') {
        telegram_sendmessage( $telegram_user_id, 'Tramite la 📎 invia per prima cosa la tua posizione');
        delete_post_meta( $plugin_post_id, 'telegram_custom_description' );
        delete_post_meta( $plugin_post_id, 'telegram_custom_state' );
        delete_post_meta( $plugin_post_id, 'telegram_last_latitude' );
        delete_post_meta( $plugin_post_id, 'telegram_last_longitude' );
    } else if ( get_post_meta( $plugin_post_id, 'telegram_custom_state', true ) == 'description_wait' && $text != '') {
      $lat = get_post_meta( $plugin_post_id, 'telegram_last_latitude', true );
      $long = get_post_meta( $plugin_post_id, 'telegram_last_longitude', true );
      if ( !$lat > 0 || !$long > 0 ) {
        telegram_sendmessage( $telegram_user_id, 'Tramite la 📎 invia per prima cosa la tua posizione');
        delete_post_meta( $plugin_post_id, 'telegram_custom_description' );
        delete_post_meta( $plugin_post_id, 'telegram_custom_state' );
        delete_post_meta( $plugin_post_id, 'telegram_last_latitude' );
        delete_post_meta( $plugin_post_id, 'telegram_last_longitude' );
        return;
      }
      update_post_meta( $plugin_post_id, 'telegram_custom_description', sanitize_text_field($text) );
      telegram_sendmessage( $telegram_user_id, 'Inviami una foto o digita "invia" per inoltrare una segnalazione di solo testo');
    }

    return;
}

function telegram_parse_photo_s ( $telegram_user_id, $photo  ) {

  $plugin_post_id = telegram_getid( $telegram_user_id );

  if ( !get_post_meta( $plugin_post_id, 'telegram_custom_description', true ) ) {
    telegram_sendmessage( $telegram_user_id, 'Errore. Inviare prima una descrizione.');
    die();
  }

    $lat = get_post_meta( $plugin_post_id, 'telegram_last_latitude', true );
    $long = get_post_meta( $plugin_post_id, 'telegram_last_longitude', true );
    if ( !$lat > 0 || !$long > 0 ) {
      telegram_sendmessage( $telegram_user_id, 'Tramite la 📎 invia per prima cosa la tua posizione');
      return;
    }

  $url = telegram_download_file( $telegram_user_id, $photo[2]['file_id'] );
  if ( $url ) {

    $desc = get_post_meta( $plugin_post_id, 'telegram_custom_description', true );

    get_post_meta($plugin_post_id, 'telegram_username', true) ? $t_user = get_post_meta($plugin_post_id, 'telegram_username', true) : $t_user = '';

    global $wpdb;

    $wpdb->insert(
        $wpdb->prefix . 'segnalazioni',
        array(
            'lat' => $lat,
            'lon' => $long,
            'telegram_id' => $telegram_user_id,
            'telegram_username' => $t_user,
            'wordpress_id' => $plugin_post_id,
            'description' => $desc,
            'url' => $url
        ),
        array( '%s', '%s', '%d', '%s', '%d', '%s', '%s' )
    );
    telegram_sendmessage( $telegram_user_id, 'Foto registrata. Grazie');
    delete_post_meta( $plugin_post_id, 'telegram_custom_description' );
    delete_post_meta( $plugin_post_id, 'telegram_custom_state' );
    delete_post_meta( $plugin_post_id, 'telegram_last_latitude' );
    delete_post_meta( $plugin_post_id, 'telegram_last_longitude' );

    telegramcustom_send_check( $wpdb->insert_id, $url, $desc );

  }
} add_action('telegram_parse_photo','telegram_parse_photo_s', 10, 2);


add_action('telegram_parse_location','telegramcustom_c_parse_location', 10, 3);

function telegramcustom_c_parse_location ( $telegram_user_id, $lat, $long  ) {
  $plugin_post_id = telegram_getid( $telegram_user_id );

  if ( !$plugin_post_id ) {
      return;
  }

  if ( get_post_meta( $plugin_post_id, 'telegram_custom_state', true ) == 'position_wait' ) {
    $int = telegram_location_haversine_distance ( 42.7, 13.24, $lat, $long, $earthRadius = 6371);
    telegram_sendmessage( $telegram_user_id, 'Posizione ricevuta ('.$int.' km dall\'epicentro)');
    telegram_sendmessage( $telegram_user_id, 'Inviami una descrizione');
    update_post_meta( $plugin_post_id, 'telegram_custom_state', 'description_wait' );
  } else {
    $int = telegram_location_haversine_distance ( 42.7, 13.24, $lat, $long, $earthRadius = 6371);
    telegram_sendmessage( $telegram_user_id, 'Posizione ricevuta ('.$int.' km dall\'epicentro)');
    telegram_sendmessage( $telegram_user_id, 'Inviami una descrizione');
    update_post_meta( $plugin_post_id, 'telegram_custom_state', 'description_wait' );
  }

}

function telegramcustom_send_check( $id, $url, $desc ) {
  $text =  $desc.PHP_EOL.PHP_EOL.$id.' - Digita ok per approvare, d:ID per eliminare, as:ID per creare issue';

  if ( $url ) {
    telegram_sendphoto( '63480147', $text, $url);
    telegram_sendphoto( '61359892', $text, $url);
    telegram_sendphoto( '166538229', $text, $url);
  } else {
    telegram_sendmessage( '63480147', $text );
    telegram_sendmessage( '61359892', $text );
    telegram_sendmessage( '166538229', $text );
  }
}

function telegramcustom_csv_pull() {

  $where = '';
  if ( isset( $_GET[ 'mese' ] ) && absint( $_GET[ 'mese' ] ) ) {
    $where = ' AND MONTH(time)='.$_GET[ 'mese' ];
  }
  if ( isset( $_GET[ 'giorno' ] ) ) {
    if ( absint( $_GET[ 'giorno' ] ) ) {
      $where .= ' AND DAY(time)='.$_GET[ 'giorno' ];
    } else if ( $_GET['giorno'] == 'oggi') {
      $where = ' AND DAY(time)='.date("d");
    }
  }

  global $wpdb;
  $file = 'segnalazioni';
  $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}segnalazioni WHERE stato=1".$where.";",ARRAY_A);

  if (empty($results)) {
    print '"id","lat","lon","telegram_id","wordpress_id","description","url","time","stato"';
    die();
  }

  $csv_output = '"'.implode('","',array_keys($results[0])).'"';

  foreach ($results as $row) {
    $csv_output .= "\r\n".'"'.implode('","',$row).'"';
  }

  if (  $_GET[ 'get-csv' ] == 'true' ) {
  $filename = $file."_".date("Y-m-d_H-i",time());
  header( "Access-Control-Allow-Origin: *");
  header( "Content-type: text/csv; charset=utf-8");
  header( "Content-disposition: csv" . date("Y-m-d") . ".csv");
  header( "Content-disposition: filename=".$filename.".csv");
}
  print $csv_output;
  die();
}

function telegram_c_plugin_parse_request() {
	global $wp_query;
	if ( isset( $_GET[ 'get-csv' ] ) ) {
    telegramcustom_csv_pull();
	}
}
add_action('template_redirect', 'telegram_c_plugin_parse_request');
?>
