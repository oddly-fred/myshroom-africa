<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;
$posts=get_posts(['post_type'=>'tranter_event','post_status'=>'any','numberposts'=>-1,'fields'=>'ids']);
foreach($posts as $id){wp_delete_post($id,true);}
foreach(['tranter_event_type','tranter_partner'] as $tax){$terms=get_terms(['taxonomy'=>$tax,'hide_empty'=>false,'fields'=>'ids']);if(!is_wp_error($terms))foreach($terms as $term)wp_delete_term($term,$tax);}
delete_option('tranter_events_settings');
